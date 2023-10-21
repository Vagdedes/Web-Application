<?php

class AccountRegistry
{

    private MethodReply $outcome;
    public const DEFAULT_WEBHOOK = "https://discord.com/api/webhooks/1165260206951911524/BmTptuNVPRxpvCaCBZcXCc5r846i-amc38zIWZpF94YZxszlE8VWj_X2NL3unsbIWPlz";

    public function __construct($applicationID, $email, $password, $name,
                                $firstName = null, $middleName = null, $lastName = null,
                                $discordWebhook = null)
    {
        $functionality = new Account($applicationID, 0);
        $functionality = $functionality->getFunctionality()->getResult(AccountFunctionality::REGISTER_ACCOUNT);

        if (!$functionality->isPositiveOutcome()) {
            $this->outcome = new MethodReply(false, $functionality->getMessage());
            return;
        }
        $parameter = new ParameterVerification($email, ParameterVerification::TYPE_EMAIL, 5, 384);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            $this->outcome = new MethodReply(false, $parameter->getOutcome()->getMessage());
            return;
        }
        $parameter = new ParameterVerification($password, null, 8, 64);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            $this->outcome = new MethodReply(false, $parameter->getOutcome()->getMessage());
            return;
        }
        $parameter = new ParameterVerification($name, null, 2, 20);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            $this->outcome = new MethodReply(false, $parameter->getOutcome()->getMessage());
            return;
        }
        $email = strtolower($email);
        $account = new Account($applicationID, null, $email);

        if ($account->exists()) {
            $this->outcome = new MethodReply(false, "Account with this email already exists.");
            return;
        }
        $account = new Account($applicationID, null, null, $name);

        if ($account->exists()) {
            $this->outcome = new MethodReply(false, "Account with this name already exists.");
            return;
        }
        global $accounts_table;

        if (!sql_insert($accounts_table,
            array(
                "email_address" => $email,
                "password" => encrypt_password($password),
                "name" => $name,
                "first_name" => $firstName,
                "middle_name" => $middleName,
                "last_name" => $lastName,
                "creation_date" => get_current_date(),
                "application_id" => $applicationID
            ))) {
            $this->outcome = new MethodReply(false, "Failed to create new account.");
            return;
        }
        $account = new Account($applicationID, null, $email, null, null, true, false);

        if (!$account->exists()) {
            $this->outcome = new MethodReply(false, "Failed to find newly created account.");
            return;
        }
        $account->clearMemory();

        if (!$account->getHistory()->add("register", null, $email)) {
            $this->outcome = new MethodReply(false, "Failed to update user history.");
            return;
        }
        $emailVerification = $account->getEmail()->initiateVerification($email);

        if (!$emailVerification->isPositiveOutcome()) {
            $message = $emailVerification->getMessage();

            if ($message !== null) {
                $this->outcome = new MethodReply(false, $message);
                return;
            }
        }
        $session = new WebsiteSession($applicationID);
        $session = $session->createSession($account);

        if (!$session->isPositiveOutcome()) {
            $this->outcome = new MethodReply(false, $session->getMessage());
            return;
        }
        $this->outcome = new MethodReply(true, "Welcome!", $account);

        if ($discordWebhook !== null) {
            send_discord_webhook_by_plan(
                "new-account",
                $discordWebhook,
                array("websiteUsername" => $name)
            );
        }
    }

    public function getOutcome(): MethodReply
    {
        return $this->outcome;
    }
}
