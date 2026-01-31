<?php

class TwoFactorAuthentication
{
    public const CODE_LENGTH = 32;

    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function initiate(?Account $account, bool $code = false): MethodReply
    {
        if ($account === null) {
            $account = $this->account;
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
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
                AccountVariables::TWO_FACTOR_AUTHENTICATION_TABLE,
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
                    $credential = random_string(self::CODE_LENGTH);
                } else {
                    $credential = random_string(AccountSession::session_token_length);

                    if (strlen($key) !== AccountSession::session_token_length) {
                        $key = $this->account->getSession()->createKey(true);
                    }
                }

                // Separator
                if (!sql_insert(
                    AccountVariables::TWO_FACTOR_AUTHENTICATION_TABLE,
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
            if (!$account->getCooldowns()->has("two_factor_authentication", true, true, false)) {
                if ($account->getEmail()->send(
                    "twoFactorAuthentication" . ($code ? "Code" : "Token"),
                    array(
                        ($code ? "code" : "URL") =>
                            ($code ? $credential : ("https://" . get_domain() . "/account/profile/twoFactorAuthentication/?token=" . $credential)),
                    ),
                    "account",
                    false
                )) {
                    $account->getCooldowns()->addInstant("two_factor_authentication", "1 minute");
                    return new MethodReply(
                        true,
                        "An authentication email has been sent as a security measurement."
                    );
                } else {
                    return new MethodReply(false, "Failed to send authentication email.");
                }
            }
            return new MethodReply(
                true,
                "An authentication email was recently sent as a security measurement."
            );
        } else {
            return new MethodReply(false, "Cannot initiate two factor authentication.");
        }
    }

    public function verify(
        ?string $token,
        ?string $code = null,
        bool    $createSession = true,
        bool    $compareAccounts = true
    ): MethodReply
    {
        if ($compareAccounts && !$this->account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        $hasCode = $code !== null;

        if ($hasCode
            || strlen($token) === AccountSession::session_token_length) {
            $date = get_current_date();
            $query = get_sql_query(
                AccountVariables::TWO_FACTOR_AUTHENTICATION_TABLE,
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
                $accountID = $this->account->getDetail("id");

                if (!$this->account->transform($object->account_id)->exists()) {
                    return new MethodReply(false, "Failed to find account.");
                }
                if ($compareAccounts
                    && $accountID !== $this->account->getDetail("id")) {
                    return new MethodReply(false, "Failed to authenticate user.");
                }
                if (!set_sql_query(
                    AccountVariables::TWO_FACTOR_AUTHENTICATION_TABLE,
                    array(
                        "completion_date" => $date
                    ),
                    array(
                        array("id", $object->id)
                    )
                )) {
                    return new MethodReply(false, "Failed to interact with the database.");
                }
                if ($createSession) {
                    if (!$this->account->getHistory()->add("instant_log_in")) {
                        return new MethodReply(false, "Failed to update user history.");
                    }
                    $session = $this->account->getSession()->create();

                    if (!$session->isPositiveOutcome()) {
                        return new MethodReply(false, $session->getMessage());
                    }
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
            AccountVariables::TWO_FACTOR_AUTHENTICATION_TABLE,
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
