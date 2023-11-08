<?php

class TwoFactorAuthentication
{
    private AccountSession $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    public function initiate(Account $account): MethodReply
    {
        global $account_sessions_table;
        $accountID = $account->getDetail("id");
        $ipAddress = get_client_ip_address();
        $array = get_sql_query(
            $account_sessions_table,
            array("id"),
            array(
                array("account_id", $accountID),
                array("ip_address", $ipAddress),
            ),
            null,
            1
        );

        if (empty($array)) {
            global $website_account_url, $instant_logins_table;
            $date = date("Y-m-d H:i:s");
            $array = get_sql_query(
                $instant_logins_table,
                array("expiration_date", "token"),
                array(
                    array("account_id", $accountID),
                    array("ip_address", $ipAddress),
                    array("completion_date", null),
                )
            );
            $token = null;

            if (!empty($array)) {
                foreach ($array as $object) {
                    if ($date <= $object->expiration_date) {
                        $token = $object->token;
                        break;
                    }
                }
            }

            if ($token === null) { // Create
                $token = random_string(AccountSession::session_token_length);

                // Separator
                $key = $this->session->createKey();

                if (strlen($key) !== AccountSession::session_token_length) {
                    $key = $this->session->createKey(true);
                }

                // Separator
                if (!sql_insert(
                    $instant_logins_table,
                    array(
                        "account_id" => $accountID,
                        "token" => $token,
                        "ip_address" => $ipAddress,
                        "access_token" => $key,
                        "creation_date" => $date,
                        "expiration_date" => get_future_date("1 hour")
                    )
                )) {
                    return new MethodReply(true, "Could not interact with database.");
                }
            }
            if ($account->getCooldowns()->addInstant("instant_login", "1 minute")) {
                $account->getEmail()->send(
                    "instantLogin",
                    array(
                        "URL" => ($website_account_url . "/profile/instantLogin/?token=" . $token)
                    ),
                    "account",
                    false
                );
            }
            return new MethodReply(
                true,
                "No recent connection in this device, a security email has been sent."
            );
        } else {
            return new MethodReply(false);
        }
    }

    public function verify($token): MethodReply
    {
        if (strlen($token) === AccountSession::session_token_length) {
            global $instant_logins_table;
            $date = get_current_date();
            $query = get_sql_query(
                $instant_logins_table,
                array("id", "account_id"),
                array(
                    array("token", $token),
                    array("completion_date", null),
                    array("expiration_date", ">", $date),
                    null,
                    array("ip_address", "=", get_client_ip_address(), 0),
                    array("access_token", $this->session->createKey()),
                    null,
                ),
                null,
                1
            );

            if (!empty($query)) {
                $object = $query[0];
                $account = new Account($this->session->getApplicationID(), $object->account_id);

                if (!$account->exists()) {
                    return new MethodReply(false, "Failed to find account.");
                }
                if (!set_sql_query(
                    $instant_logins_table,
                    array(
                        "completion_date" => $date
                    ),
                    array(
                        array("id", $object->id)
                    )
                )) {
                    return new MethodReply(false, "Failed to interact with the database.");
                }
                if (!$account->getHistory()->add("instant_log_in")) {
                    return new MethodReply(false, "Failed to update user history.");
                }
                $session = $this->session->createSession($account);

                if (!$session->isPositiveOutcome()) {
                    return new MethodReply(false, $session->getMessage());
                }
                return new MethodReply(true, null, $account);
            }
        }
        return new MethodReply(
            false,
            "Failed to authenticate user, this token is invalid or has expired."
        );
    }
}
