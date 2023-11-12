<?php

class AccountSession
{
    private ?int $applicationID;
    private ?int $customKey, $type;

    private const session_key_name = "vagdedes_account_session",
        session_account_refresh_expiration = "2 days",
        session_account_total_expiration = "15 days",
        session_max_creation_tries = 100;
    public const session_token_length = 128,
        session_cookie_expiration = 86400 * 30,
        session_cache_time = "1 minute";

    public function __construct(?int $applicationID)
    {
        $this->applicationID = $applicationID;
        $this->type = null;
        $this->customKey = null;
    }

    public function setCustomKey(int|string $type, int|string $customKey): void
    {
        $this->type = is_numeric($type) ? $type : string_to_integer($type, true);
        $this->customKey = is_numeric($customKey) ? $customKey : string_to_integer($this->customKey, true);
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

    public function getApplicationID(): ?int
    {
        return $this->applicationID;
    }

    public function getTwoFactorAuthentication(): TwoFactorAuthentication
    {
        return new TwoFactorAuthentication($this);
    }

    public function getAlive(?array $select = null, int $limit = 0): array
    {
        global $account_sessions_table;
        $date = get_current_date();
        set_sql_cache(self::session_cache_time, self::class);
        return get_sql_query(
            $account_sessions_table,
            $select,
            array(
                array("expiration_date", ">", $date),
                array("end_date", ">", $date),
                array("deletion_date", null)
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
        $cacheKey = array(self::class, $select, $limit, "all");
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            return $cache;
        }
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
            set_key_value_pair($cacheKey, $array, self::session_cache_time);
            return $array;
        } else {
            set_key_value_pair($cacheKey, $query, self::session_cache_time);
            return $query;
        }
    }

    public function createKey(bool $force = false): string
    {
        if ($this->isCustom()) {
            return $this->customKey;
        } else {
            $key = $force ? null : get_cookie(self::session_key_name);

            if ($key === null) {
                $key = random_string(self::session_token_length);
                add_cookie(self::session_key_name, $key, self::session_cookie_expiration); // Create new cookie with strict requirements
            }
            return $key;
        }
    }

    public function deleteKey(): bool
    {
        return $this->isCustom() || delete_cookie(self::session_key_name);
    }

    private function clearTokenCache(int|string $token): void
    {
        clear_memory(array(
            array(
                self::class,
                get_sql_cache_key("token", $token)
            )
        ), true, 1);
    }

    public function getSession(): MethodReply
    {
        global $account_sessions_table;
        $key = $this->createKey();
        $hasCustomKey = $this->isCustom();

        if ($hasCustomKey || strlen($key) === self::session_token_length) { // Check if length of key is correct
            $date = get_current_date();
            $key = $hasCustomKey
                ? $this->customKey
                : string_to_integer($key, true);
            set_sql_cache(null, self::class);
            $array = get_sql_query(
                $account_sessions_table,
                array("id", "account_id", "ip_address"),
                array(
                    array("type", $this->type),
                    array("token", $key),
                    array("expiration_date", ">", $date),
                    array("end_date", ">", $date),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($array)) { // Check if session exists
                $object = $array[0];
                $account = new Account($this->applicationID, $object->account_id);

                if ($account->exists()) { // Check if session account exists
                    if (!has_memory_cooldown(
                        array(self::class,
                            "refresh_session",
                            "account_id" => $object->account_id,
                            "token" => $key
                        ), self::session_cache_time)) {
                        $punishment = $account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

                        if ($punishment->isPositiveOutcome()) {
                            set_sql_query(
                                $account_sessions_table,
                                array(
                                    "deletion_date" => $date
                                ),
                                array(
                                    array("id", $object->id)
                                ),
                                null,
                                1
                            ); // Delete session from database
                            $this->clearTokenCache($key);
                            $account->clearMemory(self::class);
                            return new MethodReply(false, null, new Account($this->applicationID, 0));
                        } else {
                            set_sql_query(
                                $account_sessions_table,
                                array(
                                    "modification_date" => $date,
                                    "expiration_date" => get_future_date(self::session_account_refresh_expiration)
                                ),
                                array(
                                    array("id", $object->id)
                                ),
                                null,
                                1
                            ); // Extend expiration date of session
                        }
                    }

                    if ($object->ip_address === get_client_ip_address()
                        || $account->getPermissions()->isAdministrator()
                        && $account->getSettings()->isEnabled("two_factor_authentication")) { // Check if IP address is the same or the user is an administrator
                        return new MethodReply(true, null, $account);
                    } else {
                        global $account_sessions_table;
                        $this->createKey(true);
                        set_sql_query(
                            $account_sessions_table,
                            array(
                                "deletion_date" => $date
                            ),
                            array(
                                array("token", $key)
                            ),
                            null,
                            1
                        );
                        $this->clearTokenCache($key);
                    }
                }
            }
        } else { // Delete session cookie if key is at incorrect length
            $this->createKey(true);
        }
        return new MethodReply(false, null, new Account($this->applicationID, 0));
    }

    public function createSession(Account $account): MethodReply
    {
        $punishment = $account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

        if ($punishment->isPositiveOutcome()) {
            $this->deleteSession($account->getDetail("id"));
            return new MethodReply(false, $punishment->getMessage());
        }
        global $account_sessions_table;
        $date = get_current_date();
        $hasCustomKey = $this->isCustom();

        for ($count = 0; $count < self::session_max_creation_tries; $count++) { // Loop until a free session key is found
            $key = $this->createKey();

            if (!$hasCustomKey && strlen($key) !== self::session_token_length) { // Check if length of key is correct
                $this->createKey(true);
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
                if (!$account->getPermissions()->isAdministrator()
                    || !$account->getSettings()->isEnabled("two_factor_authentication")) {
                    $array = get_sql_query(
                        $account_sessions_table,
                        array("id"),
                        array(
                            array("account_id", $account->getDetail("id")),
                            array("deletion_date", null),
                            array("end_date", ">", $date),
                            array("expiration_date", ">", $date)
                        )
                    ); // Search for existing sessions that may be valid

                    if (!empty($array)) {
                        foreach ($array as $object) {
                            set_sql_query(
                                $account_sessions_table,
                                array(
                                    "deletion_date" => $date
                                ),
                                array(
                                    array("id", $object->id)
                                ),
                                null,
                                1
                            ); // Delete existing valid session
                        }
                        $account->clearMemory(self::class);
                    }
                }
                if (sql_insert(
                    $account_sessions_table,
                    array(
                        "type" => $this->type,
                        "token" => $key,
                        "ip_address" => get_client_ip_address(),
                        "account_id" => $account->getDetail("id"),
                        "creation_date" => $date,
                        "expiration_date" => get_future_date(self::session_account_refresh_expiration),
                        "end_date" => get_future_date(self::session_account_total_expiration)
                    )
                )) { // Insert information into the database
                    $this->clearTokenCache($key);
                    return new MethodReply(true, null, $account);
                } else {
                    $this->createKey(true);
                    $this->clearTokenCache($key);
                    return new MethodReply(false, "Failed to create session in the database.");
                }
            } else {
                $this->createKey(true);
            }
        }
        return new MethodReply(false, "Failed to find available session.");
    }

    public function deleteSession(int|string $accountID): MethodReply
    {
        $key = $this->createKey();
        $hasCustomKey = $this->isCustom();
        $this->createKey(true);

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
                    array("deletion_date", null),
                    array("end_date", ">", $date),
                    array("expiration_date", ">", $date)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            $this->clearTokenCache($key);
            $account = new Account($this->applicationID, $accountID);
            $account->clearMemory(self::class);

            if (!empty($array)) { // Check if session exists
                set_sql_query(
                    $account_sessions_table,
                    array(
                        "deletion_date" => $date
                    ),
                    array(
                        array("id", $array[0]->id)
                    ),
                    null,
                    1
                ); // Delete session from database
                return new MethodReply(true, "You have been logged out.", $account);
            }
        }
        return new MethodReply(false, "You are not logged in.");
    }
}
