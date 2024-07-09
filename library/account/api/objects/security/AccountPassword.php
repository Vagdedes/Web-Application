<?php

class AccountPassword
{
    private Account $account;

    private const tooManyChanges = 3;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function requestChange(bool $code = false, int|string $cooldown = "1 minute"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_PASSWORD, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $change_password_table;
        $date = get_current_date();
        $accountID = $this->account->getDetail("id");
        $array = get_sql_query(
            $change_password_table,
            null,
            array(
                array("account_id", $accountID),
                array("completion_date", null),
                array("expiration_date", ">", $date)
            ),
            null,
            self::tooManyChanges + 1
        );

        if (sizeof($array) >= self::tooManyChanges) {
            return new MethodReply(false, "Too many change password requests, try again later.");
        }
        $token = random_string(512);
        $createdCode = $code ? random_string(32) : null;

        if (!sql_insert(
            $change_password_table,
            array(
                "account_id" => $accountID,
                "token" => $token,
                "code" => $createdCode,
                "creation_date" => $date,
                "expiration_date" => get_future_date("8 hours")
            ))) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        if (!$this->account->getHistory()->add("request_change_password")) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::CHANGE_PASSWORD, $cooldown);
        }
        $this->account->getEmail()->send("changePassword",
            array(
                "token" => $token,
                "code" => $code ? $createdCode : "(undefined)",
            ), "account", false
        );
        return new MethodReply(true, "An email has been sent to you to change your password.");
    }

    public function completeChange(string $tokenOrCode, int|float|string $password,
                                   bool   $code = false, int|string $cooldown = "1 hour"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::COMPLETE_CHANGE_PASSWORD);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $change_password_table;
        $loggedOut = !$this->account->exists();
        $array = get_sql_query(
            $change_password_table,
            $loggedOut ? array("id", "account_id") : array("id"),
            array(
                array($code ? "code" : "token", $tokenOrCode),
                array("completion_date", null),
                array("expiration_date", ">", get_current_date())
            ),
            null,
            1
        );

        if (empty($array)) {
            return new MethodReply(false, "This change password process is invalid or has expired.");
        }
        $array = $array[0];
        $hasCooldown = $cooldown !== null;

        if ($loggedOut) { // In case the process is initiated when logged out
            $account = $this->account->getNew($array->account_id);

            if (!$account->exists()) {
                return new MethodReply(false, "Failed to find account.");
            }
        } else {
            $account = $this->account;
        }
        if ($hasCooldown) {
            $account->getFunctionality()->addInstantCooldown(AccountFunctionality::CHANGE_PASSWORD, "30 seconds");
        }
        $parameter = new ParameterVerification($password, null, 8);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $comparison = get_sql_query(
            $change_password_table,
            array("new_password"),
            array(
                array("account_id", $account->getDetail("id")),
                array("new_password", "IS NOT", null), // Not needed but added due to the past system not supporting this
                array("completion_date", "IS NOT", null),
                array("creation_date", ">", get_past_date("1 year"))
            ),
            array(
                "DESC",
                "id"
            ),
            100
        );

        if (!empty($comparison)) {
            foreach ($comparison as $row) {
                if (is_valid_password($password, $row->new_password)) {
                    return new MethodReply(false, "This password has been used in the past year.");
                }
            }
        }
        $password = encrypt_password($password);

        if (!$password) {
            return new MethodReply("Password hashing failed.");
        }
        if (!set_sql_query(
            $change_password_table,
            array(
                "completion_date" => get_current_date(),
                "new_password" => $password
            ),
            array(
                array("id", $array->id),
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        $change = $account->setDetail("password", $password);

        if (!$change->isPositiveOutcome()) {
            return new MethodReply(false, $change->getMessage());
        }
        if (!$account->getHistory()->add("complete_change_password")) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($hasCooldown) {
            $account->getFunctionality()->addInstantCooldown(AccountFunctionality::CHANGE_PASSWORD, $cooldown);
        }
        $account->getEmail()->send("passwordChanged");
        return new MethodReply(true, "Successfully changed your password.");
    }
}
