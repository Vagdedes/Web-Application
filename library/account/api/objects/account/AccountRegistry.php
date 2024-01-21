<?php

class AccountRegistry
{

    private Account $account;
    public const DEFAULT_WEBHOOK = "https://discord.com/api/webhooks/1165260206951911524/BmTptuNVPRxpvCaCBZcXCc5r846i-amc38zIWZpF94YZxszlE8VWj_X2NL3unsbIWPlz";

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function create(?string $email, ?string $password, ?string $name,
                           ?string $firstName = null, ?string $middleName = null, ?string $lastName = null,
                           ?string $discordWebhook = self::DEFAULT_WEBHOOK): MethodReply
    {
        $applicationID = $this->account->getDetail("application_id");
        $functionality = $this->account->getNew(0);
        $functionality = $functionality->getFunctionality()->getResult(AccountFunctionality::REGISTER_ACCOUNT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        $parameter = new ParameterVerification($email, ParameterVerification::TYPE_EMAIL, 5, 384);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $parameter = new ParameterVerification($password, null, 8, 64);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $parameter = new ParameterVerification($name, null, 2, 20);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $email = strtolower($email);
        $account = $this->account->getNew(null, $email);

        if ($account->exists()) {
            return new MethodReply(false, "Account with this email already exists.");
        }
        $account = $this->account->getNew(null, null, $name);

        if ($account->exists()) {
            return new MethodReply(false, "Account with this name already exists.");
        }
        global $accounts_table;

        if ($this->account->getSession()->isCustom() // Protected by captcha when not custom
            && !empty(get_sql_query(
                $accounts_table,
                array("id"),
                array(
                    array("application_id", $this->applicationID),
                    array("type", $this->account->getSession()->getType()),
                    array("custom_id", $this->account->getSession()->getCustomKey()),
                    array("deletion_date", null),
                    array("creation_date", ">", get_past_date("1 day")),
                ),
                null,
                1
            ))) {
            return new MethodReply(false, "You cannot create more accounts for now, please try again later.");
        }
        if (!sql_insert($accounts_table,
            array(
                "type" => $this->account->getSession()->getType(),
                "custom_id" => $this->account->getSession()->getCustomKey(),
                "email_address" => $email,
                "password" => encrypt_password($password),
                "name" => $name,
                "first_name" => $firstName,
                "middle_name" => $middleName,
                "last_name" => $lastName,
                "creation_date" => get_current_date(),
                "application_id" => $applicationID
            ))) {
            return new MethodReply(false, "Failed to create new account.");
        }
        $account = $this->account->getNew(null, $email, null, null, true, false);

        if (!$account->exists()) {
            return new MethodReply(false, "Failed to find newly created account.");
        }
        $account->clearMemory();

        if (!$account->getHistory()->add("register", null, $email)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $emailVerification = $account->getEmail()->initiateVerification($email, $this->account->getSession()->isCustom());

        if (!$emailVerification->isPositiveOutcome()) {
            $message = $emailVerification->getMessage();

            if ($message !== null) {
                return new MethodReply(false, $message);
            }
        }
        $session = $this->account->getSession()->create($account);

        if (!$session->isPositiveOutcome()) {
            return new MethodReply(false, $session->getMessage());
        }
        $this->outcome = new MethodReply(true, "Welcome!", $account);

        if ($discordWebhook !== null) {
            send_discord_webhook_by_plan(
                "new-account",
                $discordWebhook,
                array("websiteUsername" => $name)
            );
        }
        return $this->outcome;
    }

    public function getAccountAmount(): int
    {
        global $accounts_table;
        set_sql_cache(AccountSession::session_cache_time, self::class);
        return sizeof(
            get_sql_query(
                $accounts_table,
                array("id"),
                array(
                    array("application_id", $this->account->getDetail("application_id")),
                    array("deletion_date", null),
                )
            )
        );
    }
}
