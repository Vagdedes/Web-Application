<?php

class AccountPassword
{
    private Account $account;

    private const tooManyChanges = 3;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function requestChange(int|string $cooldown = "1 minute"): MethodReply
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

        if (!sql_insert(
            $change_password_table,
            array(
                "account_id" => $accountID,
                "token" => $token,
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
            ), "account", false
        );
        return new MethodReply(true, "An email has been sent to you to change your password.");
    }

    public function completeChange(string $token, int|float|string $password, int|string $cooldown = "1 hour"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::COMPLETE_CHANGE_PASSWORD);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $change_password_table;
        $date = get_current_date();
        $locallyLoggedIn = $this->account->getActions()->isLocallyLoggedIn();
        $isLocallyLoggedIn = $locallyLoggedIn->isPositiveOutcome();
        $loggedOut = !$this->account->exists();
        $array = get_sql_query(
            $change_password_table,
            $isLocallyLoggedIn || $loggedOut ? array("id", "account_id") : array("id"),
            array(
                array("token", $token),
                array("completion_date", null),
                array("expiration_date", ">", $date)
            ),
            null,
            1
        );

        if (empty($array)) {
            return new MethodReply(false, "This change password token is invalid or has expired.");
        }
        $array = $array[0];

        if ($isLocallyLoggedIn
            && $locallyLoggedIn->getObject()->getDetail("id") !== $array->account_id) {
            return new MethodReply(false, "This change password token is invalid.");
        }
        $parameter = new ParameterVerification($password, null, 8);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $password = encrypt_password($password);

        if (!$password) {
            return new MethodReply("Password hashing failed.");
        }
        if ($loggedOut) { // In case the process is initiated when logged out
            $this->account = new Account($this->account->getDetail("application_id"), $array->account_id);

            if (!$this->account->exists()) {
                return new MethodReply(false, "Failed to find account.");
            }
        }
        if (!set_sql_query(
            $change_password_table,
            array("completion_date" => $date),
            array(
                array("id", $array->id),
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        $change = $this->account->setDetail("password", $password);

        if (!$change->isPositiveOutcome()) {
            return new MethodReply(false, $change->getMessage());
        }
        if (!$this->account->getHistory()->add("complete_change_password")) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::CHANGE_PASSWORD, $cooldown);
        }
        $this->account->getEmail()->send("passwordChanged");
        return new MethodReply(true, "Successfully changed your password.");
    }
}
