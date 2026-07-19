<?php

class AccountRegistry
{

    public const MAX_USERNAME_LENGTH = 512;

    private Account $account;
    private int $usernameLength;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->usernameLength = 20;
    }

    public function setUsernameLength(int $length): void
    {
        $this->usernameLength = max(2, min($length, self::MAX_USERNAME_LENGTH));
    }

    public function create(
        ?string $email,
        ?string $password = null,
        ?string $name = null,
        ?string $firstName = null,
        ?string $middleName = null,
        ?string $lastName = null,
        ?string $discordWebhook = null
    ): MethodReply
    {
        $applicationID = $this->account->getDetail("application_id");
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::REGISTER_ACCOUNT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        $hasEmail = $email !== null;

        if ($hasEmail) {
            $parameter = new ParameterVerification($email, ParameterVerification::TYPE_EMAIL, 5, 384);

            if (!$parameter->getOutcome()->isPositiveOutcome()) {
                return new MethodReply(false, $parameter->getOutcome()->getMessage());
            }
        }
        if ($password !== null) {
            $parameter = new ParameterVerification($password, null, 8, 64);

            if (!$parameter->getOutcome()->isPositiveOutcome()) {
                return new MethodReply(false, $parameter->getOutcome()->getMessage());
            }
        }
        $hasName = !empty($name);

        if (!$hasName
            && !$hasEmail) {
            return new MethodReply(false, "You must provide either a name or an email address.");
        }
        if ($hasName) {
            $parameter = new ParameterVerification($name, null, 2, $this->usernameLength);

            if (!$parameter->getOutcome()->isPositiveOutcome()) {
                return new MethodReply(false, $parameter->getOutcome()->getMessage());
            }
        }
        if ($hasEmail) {
            $email = strtolower($email);

            if ($this->account->transform(null, $email)->exists()) {
                $message = "Account with this email already exists.";

                if ($this->account->getEmail()->isVerified()) {
                    return new MethodReply(false, $message);
                } else {
                    $timePassed = time() - strtotime($this->account->getDetail("creation_date"));

                    if ($timePassed < (60 * 60 * 24)) {
                        return new MethodReply(false, $message);
                    } else {
                        $this->account->getActions()->deleteAccount(false);
                    }
                }
            }
        }
        if ($hasName
            && $this->account->transform(null, null, $name)->exists()) {
            return new MethodReply(false, "Account with this name already exists.");
        }
        if (!sql_insert(
            AccountVariables::ACCOUNTS_TABLE,
            array(
                "email_address" => $email,
                "password" => $password === null ? null : encrypt_password($password),
                "name" => $name,
                "first_name" => $firstName,
                "middle_name" => $middleName,
                "last_name" => $lastName,
                "creation_date" => get_current_date(),
                "application_id" => $applicationID
            )
        )) {
            return new MethodReply(false, "Failed to create new account.");
        }
        if (!$this->account->transform(null, $email, null, null, false)->exists()) {
            return new MethodReply(false, "Failed to find newly created account.");
        }

        if (!$this->account->getHistory()->add("register", null, $email ?? $name)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($hasEmail) {
            $emailVerification = $this->account->getEmail()->initiateVerification($email, $this->account->getSession()->isCustom());

            if (!$emailVerification->isPositiveOutcome()) {
                $message = $emailVerification->getMessage();

                if ($message !== null) {
                    return new MethodReply(false, $message);
                }
            }
        }
        $session = $this->account->getSession()->create(false);

        if (!$session->isPositiveOutcome()) {
            return new MethodReply(false, $session->getMessage());
        }
        if ($discordWebhook !== null) {
            send_discord_webhook_by_plan(
                "new-account",
                $discordWebhook,
                array("websiteUsername" => $name)
            );
        }
        return new MethodReply(true, "Account successfully registered.", $this->account);
    }

    public function getAccountAmount(): int
    {
        return sizeof(
            get_sql_query(
                AccountVariables::ACCOUNTS_TABLE,
                array("id"),
                array(
                    array("application_id", $this->account->getDetail("application_id")),
                    array("deletion_date", null),
                )
            )
        );
    }

}
