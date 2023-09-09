<?php

class WebsiteSession
{
    private ?int $applicationID;

    public const session_key_name = "vagdedes_account_session",
        session_notification_key = "vagdedes_account_notification",
        session_token_length = 128,
        session_account_refresh_expiration = "2 days",
        session_account_total_expiration = "15 days",
        session_cookie_expiration = 86400 * 30;

    public function __construct($applicationID)
    {
        $this->applicationID = $applicationID;
    }

    public function getApplicationID(): ?int
    {
        return $this->applicationID;
    }

    public function getTwoFactorAuthentication(): TwoFactorAuthentication
    {
        return new TwoFactorAuthentication($this);
    }

    public function getAll($select = null, $limit = 0): array
    {
        global $account_sessions_table;
        $date = get_current_date();
        set_sql_cache(null, self::class);
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

    public function createKey(): string
    {
        $key = get_cookie(self::session_key_name);

        if ($key === null) {
            $key = random_string(self::session_token_length);
            add_cookie(self::session_key_name, $key, self::session_cookie_expiration); // Create new cookie with strict requirements
        }
        return $key;
    }

    public function deleteKey(): bool
    {
        return delete_cookie(self::session_key_name);
    }

    public function getSession(): MethodReply
    {
        global $account_sessions_table;
        $key = $this->createKey();

        if (strlen($key) === self::session_token_length) { // Check if length of key is correct
            $date = get_current_date();
            $array = get_sql_query(
                $account_sessions_table,
                array("id", "account_id", "ip_address"),
                array(
                    array("token", string_to_integer($key, true)),
                    array("expiration_date", ">", $date),
                    array("end_date", ">", $date),
                    array("deletion_date", null)
                ),
                null,
                1
            );

            if (!empty($array)) { // Check if session exists
                $object = $array[0];
                $account = new Account($this->applicationID, $object->account_id);

                if ($account->exists()) { // Check if session account exists
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
                    $punishment = $account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

                    if ($punishment->isPositiveOutcome()) {
                        return new MethodReply(true, $punishment->getMessage(), $account);
                    } else if ($object->ip_address == get_client_ip_address()
                        || $account->getPermissions()->isAdministrator()) { // Check if IP address is the same or the user is an administrator
                        return new MethodReply(true, null, $account);
                    } else {
                        global $account_sessions_table;
                        $this->deleteKey();
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
                        clear_memory(array(self::class), true);
                    }
                }
            }
        } else { // Delete database data and session cookie if key is at incorrect length
            $this->deleteKey();
            set_sql_query(
                $account_sessions_table,
                array(
                    "deletion_date" => get_current_date()
                ),
                array(
                    array("token", string_to_integer($key, true))
                ),
                null,
                1
            );
            clear_memory(array(self::class), true);
        }
        return new MethodReply(false, null, new Account($this->applicationID, 0));
    }

    public function createSession(Account $account): MethodReply
    {
        global $account_sessions_table;
        $key = $this->createKey();
        $date = get_current_date();

        while (true) { // Loop until a free session key is found
            $key = string_to_integer($key, true);
            $array = get_sql_query(
                $account_sessions_table,
                array("id"),
                array(
                    array("token", $key),
                ),
                null,
                1
            );

            if (empty($array)) { // Check if session exists
                $punishment = $account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

                if ($punishment->isPositiveOutcome()) {
                    return new MethodReply(false, $punishment->getMessage());
                }
                if (!$account->getPermissions()->isAdministrator()) {
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
                            clear_memory(array(self::class), true);
                        }
                    }
                }
                if (!has_memory_cooldown($account_sessions_table, "30 minutes")) {
                    delete_sql_query(
                        $account_sessions_table,
                        array(
                            array("end_date", "<=", get_past_date("1 month"))
                        )
                    ); // Delete old sessions to speed up performance
                }
                if (sql_insert(
                    $account_sessions_table,
                    array(
                        "token" => $key,
                        "ip_address" => get_client_ip_address(),
                        "account_id" => $account->getDetail("id"),
                        "creation_date" => $date,
                        "expiration_date" => get_future_date(self::session_account_refresh_expiration),
                        "end_date" => get_future_date(self::session_account_total_expiration)
                    )
                )) { // Insert information into the database
                    clear_memory(array(self::class), true);
                    return new MethodReply(true, null, $account);
                } else {
                    return new MethodReply(false, "Failed to create session.");
                }
            } else { // Delete database data and session cookie if it already exists
                $this->deleteKey();
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
                clear_memory(array(self::class), true);
            }
        }
    }

    public function deleteSession($accountID): MethodReply
    {
        global $account_sessions_table;
        $key = $this->createKey();

        if (strlen($key) === self::session_token_length) { // Check if length of key is correct
            $date = get_current_date();
            $array = get_sql_query(
                $account_sessions_table,
                array("id"),
                array(
                    array("token", "=", string_to_integer($key, true), 0),
                    array("account_id", $accountID),
                    array("deletion_date", null),
                    array("end_date", ">", $date),
                    array("expiration_date", ">", $date)
                ),
                null,
                1
            );

            if (!empty($array)) { // Check if session exists
                $object = $array[0];
                $this->deleteKey(); // Delete session cookie
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
                clear_memory(array(self::class), true);
                return new MethodReply(true, null, new Account($this->applicationID, $accountID));
            }
        } else { // Delete database data and session cookie if key is at incorrect length
            $this->deleteKey();
            set_sql_query(
                $account_sessions_table,
                array(
                    "deletion_date" => get_current_date()
                ),
                array(
                    array("token", $key)
                ),
                null,
                1
            );
            clear_memory(array(self::class), true);
        }
        return new MethodReply(false);
    }
}
