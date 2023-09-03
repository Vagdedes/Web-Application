<?php

class AccountPassword
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function requestChange(): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::CHANGE_PASSWORD,
            $this->account
        );
        $functionalityOutcome = $functionality->getResult();

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $change_password_table;
        $date = get_current_date();
        $accountID = $this->account->getDetail("id");
        $array = get_sql_query(
            $change_password_table,
            array(),
            array(
                array("account_id", $accountID),
                array("completion_date", null),
                array("expiration_date", ">", $date)
            ),
            null,
            4
        );

        if (sizeof($array) >= 3) {
            return new MethodReply(false, "Too many change password requests, try again later.");
        }
        $token = random_string(1024);

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
        $functionality->addUserCooldown("15 minutes");
        $this->account->getEmail()->send("changePassword",
            array(
                "token" => $token,
            ), "account", false
        );
        return new MethodReply(true, "An email has been sent to you to change your password.");
    }

    public function completeChange($token, $password): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::CHANGE_PASSWORD,
            $this->account
        );
        $functionalityOutcome = $functionality->getResult(true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $change_password_table;
        $date = get_current_date();
        $array = get_sql_query(
            $change_password_table,
            array("id"),
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
        $password = encrypt_password($password);

        if (!$password) {
            return new MethodReply("Password hashing failed.");
        }
        if (!set_sql_query(
            $change_password_table,
            array("completion_date" => $date),
            array(
                array("id", $array[0]->id),
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
        $this->account->getEmail()->send("passwordChanged");
        return new MethodReply(true, "Successfully changed your password.");
    }
}
