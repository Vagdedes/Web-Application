<?php

class AccountEmail
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function requestVerification($email, $cooldown = "1 minute"): MethodReply
    {
        if (!is_email($email)) {
            return new MethodReply(false, "Please enter a valid email address.");
        }
        $email = strtolower($email);
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_EMAIL, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        $currentEmail = $this->account->getDetail("email_address");

        if ($email === strtolower($currentEmail)) {
            return new MethodReply(false, "This is already your email address.");
        }
        global $accounts_table;

        if (!empty(get_sql_query(
            $accounts_table,
            array("id"),
            array(
                array("email_address", $email),
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id"))
            ),
            null,
            1
        ))) {
            return new MethodReply(false, "This email address is already in use by another user.");
        }
        if (!$this->account->getHistory()->add("request_email_verification", $currentEmail, $email)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $result = $this->initiateVerification($email);
        $resultOutcome = $result->isPositiveOutcome();

        if ($resultOutcome) {
            if ($cooldown !== null) {
                $functionality->addInstantCooldown(AccountFunctionality::CHANGE_EMAIL, $cooldown);
            }
        }
        return new MethodReply($resultOutcome, $result->getMessage());
    }

    public function completeVerification($token, $cooldown = "1 day"): MethodReply
    {
        $account = $this->account;
        $exists = $account->exists();

        if ($exists) {
            $functionality = $account->getFunctionality();
            $functionalityOutcome = $functionality->getResult(AccountFunctionality::COMPLETE_EMAIL_VERIFICATION);

            if (!$functionalityOutcome->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        global $email_verifications_table;
        $date = get_current_date();
        $array = get_sql_query(
            $email_verifications_table,
            $exists ? array("id", "email_address") : array("id", "email_address", "account_id"),
            array(
                array("token", $token),
                array("completion_date", null),
                array("expiration_date", ">", $date)
            ),
            null,
            1
        );

        if (empty($array)) {
            return new MethodReply(false, "This email verification token is invalid or has expired.");
        }
        global $accounts_table;
        $object = $array[0];
        $applicationID = $account->getDetail("application_id");

        if (!$exists) {
            $account = new Account($applicationID, $object->account_id);

            if (!$account->exists()) {
                return new MethodReply(false, "Failed to find account related to email.");
            }
            $functionality = $account->getFunctionality();
            $functionalityOutcome = $functionality->getResult(AccountFunctionality::COMPLETE_EMAIL_VERIFICATION);

            if (!$functionalityOutcome->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        $email = $object->email_address;

        if ($account->getDetail("email_address") != $email
            && !empty(get_sql_query(
                $accounts_table,
                array("id"),
                array(
                    array("email_address", $email),
                    array("deletion_date", null),
                    array("application_id", $applicationID)
                ),
                null,
                1
            ))) {
            return new MethodReply(false, "This email address is already in use by another user.");
        }
        $verifiedEmail = $account->getEmail()->isVerified();

        if (!set_sql_query(
            $email_verifications_table,
            array("completion_date" => $date),
            array(
                array("id", $object->id),
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        $account->clearMemory(self::class);
        $oldEmail = $account->getDetail("email_address");
        $change = $account->setDetail("email_address", $email);

        if (!$change->isPositiveOutcome()) {
            return new MethodReply(false, $change->getMessage());
        }
        if (!$account->getHistory()->add("complete_email_verification", $oldEmail, $email)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $account->getEmail()->send(
            $verifiedEmail ? "emailChanged" : "emailVerified",
            array(
                "email" => $email,
            ),
            "account",
            false
        );
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::CHANGE_EMAIL, $cooldown);
        }
        return new MethodReply(true, "Your email verification has been successfully completed.");
    }

    public function initiateVerification($email = null): MethodReply
    {
        if ($email === null) {
            if ($this->isVerified()) {
                return new MethodReply(true, "Your email address is already verified.");
            }
            $email = $this->account->getDetail("email_address");
        } else {
            $parameter = new ParameterVerification($email, ParameterVerification::TYPE_EMAIL, 5, 384);

            if (!$parameter->getOutcome()->isPositiveOutcome()) {
                return new MethodReply(false, $parameter->getOutcome()->getMessage());
            }
            $email = strtolower($email);
        }
        global $email_verifications_table;
        $accountID = $this->account->getDetail("id");
        $date = get_current_date();
        $array = get_sql_query(
            $email_verifications_table,
            array("token"),
            array(
                array("account_id", $accountID),
                array("completion_date", null),
                array("email_address", $email),
                array("expiration_date", ">", $date)
            )
        );

        if (empty($array)) {
            $token = random_string(512);

            if (!sql_insert(
                $email_verifications_table,
                array(
                    "token" => $token,
                    "account_id" => $accountID,
                    "email_address" => $email,
                    "creation_date" => $date,
                    "expiration_date" => get_future_date("7 days")
                ))) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            $this->account->clearMemory(self::class);
        } else {
            $token = $array[0]->token;
        }
        $this->send(
            "verifyEmail",
            array(
                "token" => $token,
            ),
            "account",
            false
        );
        return new MethodReply(true, "A verification email has been sent to your email.");
    }

    public function isVerified(): bool
    {
        global $email_verifications_table;
        set_sql_cache(null, self::class);
        return !empty(get_sql_query(
            $email_verifications_table,
            array("id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("completion_date", "IS NOT", null),
                null,
                array("email_address", "IS", null, 0), // Support for old system structure
                array("email_address", $this->account->getDetail("email_address")),
                null,
            ),
            null,
            1
        ));
    }

    public function send($case, $detailsArray = null, $type = "account", $unsubscribe = true): bool
    {
        $applicationID = $this->account->getDetail("application_id");

        if ($applicationID === null) {
            $applicationID = 0;
        }
        return $this->account->getSettings()->isEnabled(
                "receive_" . $type . "_emails",
                $type === "account"
            )
            && send_email_by_plan(
                $applicationID . "-" . $case,
                $this->account->getDetail("email_address"),
                $detailsArray,
                $unsubscribe
            ) === 1;
    }
}
