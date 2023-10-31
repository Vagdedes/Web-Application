<?php

class AccountRegistry
{

    private MethodReply $outcome;
    public const DEFAULT_WEBHOOK = "https://discord.com/api/webhooks/1165260206951911524/BmTptuNVPRxpvCaCBZcXCc5r846i-amc38zIWZpF94YZxszlE8VWj_X2NL3unsbIWPlz";

    public function __construct(?int            $applicationID, ?string $email, ?string $password, ?string $name,
                                ?string         $firstName = null, ?string $middleName = null, ?string $lastName = null,
                                ?AccountSession $session = null,
                                ?string         $discordWebhook = self::DEFAULT_WEBHOOK)
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

        if ($session === null) {
            $session = new AccountSession($applicationID);
            $session->setCustomKey("website", get_client_ip_address());
        }
        if ($session->isCustom() // Protected by captcha when not custom
            && !empty(get_sql_query(
                $accounts_table,
                array("id"),
                array(
                    array("application_id", $session->getApplicationID()),
                    array("type", $session->getType()),
                    array("custom_id", $session->getCustomKey()),
                    array("deletion_date", null),
                    array("creation_date", ">", get_past_date("1 day")),
                ),
                null,
                1
            ))) {
            $this->outcome = new MethodReply(false, "You cannot create more accounts for now, please try again later.");
            return;
        }
        if (!sql_insert($accounts_table,
            array(
                "type" => $session->getType(),
                "custom_id" => $session->getCustomKey(),
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

    public static function getAccountAmount(?int $applicationID): int
    {
        global $accounts_table;
        set_sql_cache(AccountSession::session_cache_time, self::class);
        return sizeof(get_sql_query(
            $accounts_table,
            array("id"),
            array(
                array("application_id", $applicationID),
                array("deletion_date", null),
            ))
        );
    }
}
