<?php

class AccountSession
{
    private Account $account;
    private ?int $customKey, $type;

    private const
        session_key_name = "vagdedes_account_session",
        session_account_refresh_expiration = "2 days",
        session_account_total_expiration = "15 days",
        session_max_creation_tries = 100;

    public const
        session_token_length = 128,
        session_cookie_expiration = 86400 * 3;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->type = null;
        $this->customKey = null;
    }

    public function setCustomKey(int|string|null $type, int|string|null $customKey): void
    {
        $this->type = $type === null ? null :
            (is_numeric($type) ? $type : string_to_integer($type, true));
        $this->customKey = $customKey === null ? null :
            (is_numeric($customKey) ? $customKey : string_to_integer($this->customKey, true));
    }

    public function isCustom(): bool
    {
        return $this->type !== null && $this->customKey !== null;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function getCustomKey(): ?int
    {
        return $this->customKey;
    }

    public function getAlive(?array $select = null, int $limit = 0): array
    {
        global $account_sessions_table;
        $date = get_current_date();
        return get_sql_query(
            $account_sessions_table,
            $select,
            array(
                array("expiration_date", ">", $date),
            ),
            array(
                "DESC",
                "expiration_date"
            ),
            $limit
        );
    }

    public function getAll(array $select = array("account_id"), int $limit = 0): array
    {
        global $account_sessions_table;
        $query = get_sql_query(
            $account_sessions_table,
            $select,
            null,
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            $array = array();

            foreach ($query as $row) {
                if (!array_key_exists($row->account_id, $array)) {
                    $array[$row->account_id] = $row;
                }
            }
            return $array;
        } else {
            return $query;
        }
    }

    public function createKey(bool $force = false): string
    {
        if ($this->isCustom()) {
            return $this->customKey;
        } else if ($force) {
            $this->deleteKey();
            $key = random_string(self::session_token_length);
            add_cookie(self::session_key_name, $key, self::session_cookie_expiration); // Create new cookie with strict requirements
            return $key;
        } else {
            $key = get_cookie(self::session_key_name);

            if ($key === null) {
                $key = random_string(self::session_token_length);
                add_cookie(self::session_key_name, $key, self::session_cookie_expiration); // Create new cookie with strict requirements
            }
            return $key;
        }
    }

    public function deleteKey(): bool
    {
        return $this->isCustom() || delete_all_cookies();
    }

    public function find(bool $checkIpAddress = true): MethodReply
    {
        global $account_sessions_table;
        $key = $this->createKey();
        $hasCustomKey = $this->isCustom();

        if ($hasCustomKey || strlen($key) === self::session_token_length) { // Check if length of key is correct
            $date = get_current_date();
            $key = $hasCustomKey
                ? $this->customKey
                : string_to_integer($key, true);
            $array = get_sql_query(
                $account_sessions_table,
                array("id", "account_id", "ip_address", "creation_date"),
                array(
                    array("type", $this->type),
                    array("token", $key),
                    array("expiration_date", ">", $date),
                    $checkIpAddress ? array("ip_address", get_client_ip_address()) : ""
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($array)) { // Check if session exists
                $object = $array[0];
                $account = $this->account->getNew($object->account_id);

                if ($account->exists()) { // Check if session account exists
                    $maxTime = strtotime($object->creation_date) + strtotime(self::session_account_total_expiration);
                    set_sql_query(
                        $account_sessions_table,
                        array(
                            "expiration_date" => min(
                                get_future_date(self::session_account_refresh_expiration),
                                date('Y-m-d H:i:s', $maxTime)
                            )
                        ),
                        array(
                            array("id", $object->id)
                        ),
                        null,
                        1
                    ); // Extend expiration date of session
                    return new MethodReply(true, null, $account);
                }
            }
        } else { // Delete session cookie if key is at incorrect length
            $this->deleteKey();
        }
        return new MethodReply(
            false,
            null,
            $this->account->exists() ? $this->account->getNew(0) : $this->account
        );
    }

    public function create(): MethodReply
    {
        $punishment = $this->account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

        if ($punishment->isPositiveOutcome()) {
            $this->delete();
            return new MethodReply(false, $punishment->getMessage());
        }
        global $account_sessions_table;
        $date = get_current_date();
        $hasCustomKey = $this->isCustom();

        for ($count = 0; $count < self::session_max_creation_tries; $count++) { // Loop until a free session key is found
            $key = $this->createKey();

            if (!$hasCustomKey && strlen($key) !== self::session_token_length) { // Check if length of key is correct
                $this->deleteKey();
                continue;
            }
            $key = $hasCustomKey
                ? $this->customKey
                : string_to_integer($key, true);
            $array = $hasCustomKey ? null
                : get_sql_query(
                    $account_sessions_table,
                    array("id"),
                    array(
                        array("token", $key),
                    ),
                    null,
                    1
                );

            if (empty($array)) { // Check if session does not exist
                if (!$this->account->getPermissions()->isAdministrator()
                    || !$this->account->getSettings()->isEnabled("two_factor_authentication")) {
                    $array = get_sql_query(
                        $account_sessions_table,
                        array("id"),
                        array(
                            array("account_id", $this->account->getDetail("id")),
                            array("expiration_date", ">", $date)
                        )
                    ); // Search for existing sessions that may be valid

                    if (!empty($array)) {
                        foreach ($array as $object) {
                            set_sql_query(
                                $account_sessions_table,
                                array(
                                    "expiration_date" => $date
                                ),
                                array(
                                    array("id", $object->id)
                                ),
                                null,
                                1
                            ); // Delete existing valid session
                        }
                    }
                }
                if (sql_insert(
                    $account_sessions_table,
                    array(
                        "type" => $this->type,
                        "token" => $key,
                        "ip_address" => get_client_ip_address(),
                        "account_id" => $this->account->getDetail("id"),
                        "creation_date" => $date,
                        "expiration_date" => get_future_date(self::session_account_refresh_expiration),
                    )
                )) { // Insert information into the database
                    return new MethodReply(true);
                } else {
                    $this->deleteKey();
                    return new MethodReply(false, "Failed to create session in the database.");
                }
            } else {
                $this->deleteKey();
            }
        }
        return new MethodReply(false, "Failed to find available session.");
    }

    public function delete(): MethodReply
    {
        if ($this->account->exists()) {
            $key = $this->createKey();
            $hasCustomKey = $this->isCustom();
            $this->deleteKey();

            if ($hasCustomKey || strlen($key) === self::session_token_length) { // Check if length of key is correct
                global $account_sessions_table;
                $key = $hasCustomKey
                    ? $this->customKey
                    : string_to_integer($key, true);
                $date = get_current_date();
                $array = get_sql_query(
                    $account_sessions_table,
                    array("id"),
                    array(
                        array("type", $this->type),
                        array("token", $key),
                        array("expiration_date", ">", $date)
                    ),
                    array(
                        "DESC",
                        "id"
                    ),
                    1
                );

                $this->account->getHistory()->add("log_out");

                if (!empty($array)) { // Check if session exists
                    set_sql_query(
                        $account_sessions_table,
                        array(
                            "expiration_date" => $date
                        ),
                        array(
                            array("id", $array[0]->id)
                        ),
                        null,
                        1
                    ); // Delete session from database
                    return new MethodReply(true, "You have been logged out.");
                }
            }
        }
        return new MethodReply(false, "You are not logged in.");
    }

    public function getLastKnown(): ?object
    {
        global $account_sessions_table;
        $query = get_sql_query(
            $account_sessions_table,
            null,
            array(
                array("type", $this->type),
                array("token", $this->customKey),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        return !empty($query) ? $query[0] : null;
    }
}
