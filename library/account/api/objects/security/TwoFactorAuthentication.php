<?php

class TwoFactorAuthentication
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function initiate(Account $account, bool $code = false): MethodReply
    {
        $accountID = $account->getDetail("id");
        $ipAddress = get_client_ip_address();
        $array = empty($ipAddress) ? null : get_sql_query(
            AccountVariables::SESSIONS_TABLE,
            array("id"),
            array(
                array("account_id", $accountID),
                array("ip_address", $ipAddress),
            ),
            null,
            1
        );

        if (empty($array)) {
            $date = date("Y-m-d H:i:s");
            $array = get_sql_query(
                AccountVariables::INSTANT_LOGINS_TABLE,
                array("expiration_date", "token"),
                array(
                    array("account_id", $accountID),
                    array("ip_address", $ipAddress),
                    array("completion_date", null),
                )
            );
            $credential = null;

            if (!empty($array)) {
                foreach ($array as $object) {
                    if ($date <= $object->expiration_date) {
                        $credential = $code ? $object->code : $object->token;
                        break;
                    }
                }
            }

            if ($credential === null) { // Create
                $key = $this->account->getSession()->createKey();

                if ($code) {
                    $credential = random_string(32);
                } else {
                    $credential = random_string(AccountSession::session_token_length);

                    if (strlen($key) !== AccountSession::session_token_length) {
                        $key = $this->account->getSession()->createKey(true);
                    }
                }

                // Separator
                if (!sql_insert(
                    AccountVariables::INSTANT_LOGINS_TABLE,
                    array(
                        "account_id" => $accountID,
                        ($code ? "code" : "token") => $credential,
                        "ip_address" => $ipAddress,
                        "access_token" => $key,
                        "creation_date" => $date,
                        "expiration_date" => get_future_date("1 hour")
                    )
                )) {
                    return new MethodReply(false, "Could not interact with database.");
                }
            }
            if ($account->getCooldowns()->addInstant("instant_login", "1 minute")) {
                $account->getEmail()->send(
                    "instantLogin" . ($code ? "Code" : "Token"),
                    array(
                        ($code ? "code" : "URL") =>
                            ($code ? $credential : ("https://" . get_domain() . "/account/profile/instantLogin/?token=" . $credential)),
                    ),
                    "account",
                    false
                );
            }
            return new MethodReply(
                true,
                "A authentication email has been sent as a security measurement."
            );
        } else {
            return new MethodReply(false);
        }
    }

    public function verify(?string $token, ?string $code = null): MethodReply
    {
        $hasCode = $code !== null;

        if ($hasCode || strlen($token) === AccountSession::session_token_length) {
            $date = get_current_date();
            $query = get_sql_query(
                AccountVariables::INSTANT_LOGINS_TABLE,
                array("id", "account_id"),
                array(
                    $hasCode ? array("code", $code) : array("token", $token),
                    array("completion_date", null),
                    array("expiration_date", ">", $date),
                    null,
                    array("ip_address", "=", get_client_ip_address(), 0),
                    array("access_token", $this->account->getSession()->createKey()),
                    null,
                ),
                null,
                1
            );

            if (!empty($query)) {
                $object = $query[0];

                if (!$this->account->transform($object->account_id)->exists()) {
                    return new MethodReply(false, "Failed to find account.");
                }
                if (!set_sql_query(
                    AccountVariables::INSTANT_LOGINS_TABLE,
                    array(
                        "completion_date" => $date
                    ),
                    array(
                        array("id", $object->id)
                    )
                )) {
                    return new MethodReply(false, "Failed to interact with the database.");
                }
                if (!$this->account->getHistory()->add("instant_log_in")) {
                    return new MethodReply(false, "Failed to update user history.");
                }
                $session = $this->account->getSession()->create();

                if (!$session->isPositiveOutcome()) {
                    return new MethodReply(false, $session->getMessage());
                }
                return new MethodReply(true, "Successfully verified account.");
            }
        }
        return new MethodReply(
            false,
            "Failed to authenticate user, this " . ($hasCode ? "code" : "token") . " is invalid or has expired."
        );
    }

    public function isPending(): bool
    {
        return !empty(get_sql_query(
            AccountVariables::INSTANT_LOGINS_TABLE,
            array("id", "account_id"),
            array(
                array("completion_date", null),
                array("expiration_date", ">", get_current_date()),
                array("account_id", $this->account->getDetail("id")),
                array("ip_address", get_client_ip_address()),
            ),
            null,
            1
        ));
    }
}
