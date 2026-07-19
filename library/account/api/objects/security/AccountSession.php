<?php

class AccountSession
{
    private Account $account;

    private const
        session_key_name = "1brpfgiovljnklabu21p_account_session",
        session_max_creation_tries = 100;

    public const
        session_account_refresh_expiration = "2 days",
        session_account_total_expiration = "15 days",
        session_token_length = 128,
        session_cookie_expiration = 86400 * 3;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getAlive(?array $select = null, int $limit = 0): array
    {
        $date = get_current_date();
        return get_sql_query(
            AccountVariables::SESSIONS_TABLE,
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
        $query = get_sql_query(
            AccountVariables::SESSIONS_TABLE,
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
        if ($force) {
            $key = random_string(self::session_token_length);
            add_cookie(self::session_key_name, $key, self::session_cookie_expiration); // Create new cookie with strict requirements
        } else {
            $key = get_cookie(self::session_key_name);

            if ($key === null) {
                $key = random_string(self::session_token_length);
                add_cookie(self::session_key_name, $key, self::session_cookie_expiration); // Create new cookie with strict requirements
            }
        }
        return $key;
    }

    public function refreshKey(): string
    {
        return $this->createKey(true);
    }

    public function find(bool $checkIpAddress = true): MethodReply
    {
        $key = $this->createKey();

        if (strlen($key) === self::session_token_length) { // Check if length of key is correct
            $date = get_current_date();
            $key = string_to_integer($key, true);
            $array = get_sql_query(
                AccountVariables::SESSIONS_TABLE,
                array("id", "account_id", "ip_address", "creation_date"),
                array(
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
                $maxTime = strtotime($object->creation_date) + strtotime(self::session_account_total_expiration);
                set_sql_query(
                    AccountVariables::SESSIONS_TABLE,
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
                $this->account->transform($object->account_id);
                return new MethodReply(
                    true,
                    "Account session found successfully."
                );
            }
        } else { // Delete session cookie if key is at incorrect length
            $this->refreshKey();
        }
        return new MethodReply(
            false,
            "Account session not found."
        );
    }

    public function create(bool $allowMultiple, ?string $key = null): MethodReply
    {
        $punishment = $this->account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

        if ($punishment->isPositiveOutcome()) {
            $this->delete();
            return new MethodReply(false, $punishment->getMessage());
        }
        $date = get_current_date();

        if ($key === null) {
            $key = $this->createKey();
        }
        for ($count = 0; $count < self::session_max_creation_tries; $count++) { // Loop until a free session key is found
            if (strlen($key) !== self::session_token_length) { // Check if length of key is correct
                $key = $this->refreshKey();
                continue;
            }
            $key = string_to_integer($key, true);
            $array = get_sql_query(
                AccountVariables::SESSIONS_TABLE,
                array("id"),
                array(
                    array("token", $key)
                ),
                null,
                1
            );

            if (empty($array)) { // Check if session does not exist
                if (!$allowMultiple) {
                    $array = get_sql_query(
                        AccountVariables::SESSIONS_TABLE,
                        array("id"),
                        array(
                            array("account_id", $this->account->getDetail("id")),
                            array("expiration_date", ">", $date)
                        )
                    ); // Search for existing sessions that may be valid

                    if (!empty($array)) {
                        foreach ($array as $object) {
                            set_sql_query(
                                AccountVariables::SESSIONS_TABLE,
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
                    AccountVariables::SESSIONS_TABLE,
                    array(
                        "token" => $key,
                        "ip_address" => get_client_ip_address(),
                        "account_id" => $this->account->getDetail("id"),
                        "creation_date" => $date,
                        "expiration_date" => get_future_date(self::session_account_refresh_expiration),
                    )
                )) { // Insert information into the database
                    if ($this->account->exists()
                        && !$this->account->getHistory()->add("instant_log_in")) {
                        return new MethodReply(false, "Failed to update user history.");
                    }
                    return new MethodReply(true, "Session created successfully.");
                } else {
                    $this->refreshKey();
                    return new MethodReply(false, "Failed to create session in the database.");
                }
            } else {
                $this->refreshKey();
            }
        }
        return new MethodReply(false, "Failed to find available session.");
    }

    public function delete(): MethodReply
    {
        $key = $this->createKey();
        $this->refreshKey();

        if (strlen($key) === self::session_token_length) { // Check if length of key is correct
            $key = string_to_integer($key, true);
            $date = get_current_date();
            $array = get_sql_query(
                AccountVariables::SESSIONS_TABLE,
                array("id"),
                array(
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
                if (set_sql_query(
                    AccountVariables::SESSIONS_TABLE,
                    array(
                        "expiration_date" => $date
                    ),
                    array(
                        array("id", $array[0]->id)
                    ),
                    null,
                    1
                )) { // Delete session from database
                    return new MethodReply(true, "You have been logged out.");
                } else {
                    return new MethodReply(false, "Failed to delete session from the database.");
                }
            }
        }
        return new MethodReply(true, "You are not logged in.");
    }

}
