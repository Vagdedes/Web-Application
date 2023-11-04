<?php

class AccountActions
{
    private Account $account;

    private const log_in_out_cooldown = "3 seconds";
    public const
        NAME = "name",
        FIRST_NAME = "first_name",
        MIDDLE_NAME = "middle_name",
        LAST_NAME = "last_name";

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function logIn($password, ?AccountSession $session = null, bool $twoFactor = true): MethodReply
    {
        $parameter = new ParameterVerification($password, null, 8);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::LOG_IN, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!is_valid_password($password, $this->account->getDetail("password"))) {
            return new MethodReply(false, "Incorrect account password");
        }
        $punishment = $this->account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

        if ($punishment->isPositiveOutcome()) {
            return new MethodReply(false, $punishment->getMessage());
        }
        if ($session === null) {
            $session = new AccountSession($this->account->getDetail("application_id"));
        }
        if ($twoFactor
            && $this->account->getSettings()->isEnabled("two_factor_authentication")) {
            $twoFactor = $session->getTwoFactorAuthentication();
            $twoFactor = $twoFactor->initiate($this->account);

            if ($twoFactor->isPositiveOutcome()) {
                return new MethodReply(false, $twoFactor->getMessage());
            }
        }
        if (!$this->account->getHistory()->add("log_in")) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $session = $session->createSession($this->account);

        if (!$session->isPositiveOutcome()) {
            return new MethodReply(false, $session->getMessage());
        }
        $functionality->addInstantCooldown(AccountFunctionality::LOG_IN, self::log_in_out_cooldown);
        $this->account->refresh();
        return new MethodReply(true, "You have been logged in.");
    }

    public function logOut(?AccountSession $session = null): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::LOG_OUT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if ($session === null) {
            $session = new AccountSession($this->account->getDetail("application_id"));
        }
        $session = $session->deleteSession($this->account->getDetail("id"));

        if ($session->isPositiveOutcome()) {
            $functionality->addInstantCooldown(AccountFunctionality::LOG_OUT, self::log_in_out_cooldown);
            $session->getObject()->getHistory()->add("log_out");
            return new MethodReply(true, $session->getMessage());
        } else {
            return new MethodReply(false, $session->getMessage());
        }
    }

    public function isLocallyLoggedIn(): MethodReply
    {
        $session = new AccountSession($this->account->getDetail("application_id"));

        if ($session->getSession()->isPositiveOutcome()) {
            $session = $session->getSession()->getObject();

            if ($this->account->getDetail("id") == $session->getDetail("id")) {
                return new MethodReply(true, null, $session);
            }
        }
        return new MethodReply(false);
    }

    public function deleteAccount($permanently = false): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::DELETE_ACCOUNT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if ($permanently) {
            $tables = get_sql_database_tables("account");

            if (!empty($tables)) {
                global $accounts_table;
                $accountID = $this->account->getDetail("id");

                if (delete_sql_query(
                    $accounts_table,
                    array(
                        array("id", $accountID)
                    )
                )) {
                    foreach ($tables as $table) {
                        if ($table !== $accounts_table) {
                            delete_sql_query($table,
                                array(
                                    array("account_id", $accountID)
                                )
                            );
                        }
                    }
                    $this->account->clearMemory();
                    return new MethodReply(true, "User successfully deleted permanently.");
                }
                return new MethodReply(false, "Failed to delete account.");
            }
            return new MethodReply(false, "Failed to find database tables.");
        } else {
            global $accounts_table;
            if (!set_sql_query(
                $accounts_table,
                array("deletion_date" => get_current_date()),
                array(
                    array("id", $this->account->getDetail("id"))
                ),
                null,
                1
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            $this->account->clearMemory();
            return new MethodReply(true, "User successfully deleted.");
        }
    }

    public function changeName($name, $type = self::NAME, $cooldown = "1 day"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_NAME, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->getEmail()->isVerified()) {
            if (!$this->account->getEmail()->initiateVerification()->isPositiveOutcome()) {
                return new MethodReply(false, "You must verify your email first.");
            }
            return new MethodReply(false, "You must verify your email first, an email has been sent to you.");
        }
        $parameter = new ParameterVerification($name, null, 2, 20);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        if ($name === $this->account->getDetail($type)) {
            return new MethodReply(false, "You already have this name.");
        }
        global $accounts_table;

        if (!empty(get_sql_query(
            $accounts_table,
            array("id"),
            array(
                array($type, $name),
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id"))
            ),
            null,
            1
        ))) {
            return new MethodReply(false, "This name is already taken.");
        }
        $change = $this->account->setDetail($type, $name);

        if (!$change->isPositiveOutcome()) {
            return new MethodReply(false, $change->getMessage());
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::CHANGE_NAME, $cooldown);
        }
        $this->account->getEmail()->send("nameChanged",
            array(
                "newName" => $name
            )
        );
        return new MethodReply(true, "Name successfully changed to '$name'.");
    }
}
