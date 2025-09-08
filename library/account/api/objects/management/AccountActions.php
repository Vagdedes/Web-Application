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

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function logIn(?string $password = null, ?string $twoFactorCode = null): MethodReply
    {
        if ($password !== null) {
            $parameter = new ParameterVerification($password, null, 8);

            if (!$parameter->getOutcome()->isPositiveOutcome()) {
                return new MethodReply(false, $parameter->getOutcome()->getMessage());
            }
            $passwordAgainst = $this->account->getDetail("password");

            if ($passwordAgainst === null) {
                return new MethodReply(false, "Account password is not set.");
            } else if (!is_valid_password($password, $passwordAgainst)) {
                return new MethodReply(false, "Incorrect account password");
            }
        }
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::LOG_IN, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        $punishment = $this->account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

        if ($punishment->isPositiveOutcome()) {
            return new MethodReply(false, $punishment->getMessage());
        }
        $twoFactor = $this->account->getSettings()->isEnabled("two_factor_authentication");

        if (!$twoFactor && $this->account->getSession()->isCustom()) {
            $object = $this->account->getSession()->getLastKnown();
            $twoFactor = $object === null || $object->account_id != $this->account->getDetail("id");
        }
        if ($twoFactor) {
            if (empty($twoFactorCode)) {
                $twoFactor = $this->account->getTwoFactorAuthentication()->initiate(
                    $this->account,
                    $twoFactorCode !== null
                );

                if ($twoFactor->isPositiveOutcome()) {
                    return new MethodReply(false, $twoFactor->getMessage());
                }
            } else {
                $twoFactor = $this->account->getTwoFactorAuthentication()->verify(null, $twoFactorCode);

                if (!$twoFactor->isPositiveOutcome()) {
                    return new MethodReply(false, $twoFactor->getMessage());
                }
            }
        }
        if (!$this->account->getHistory()->add("log_in")) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $session = $this->account->getSession()->find();

        if (!$session->isPositiveOutcome()) {
            $session = $this->account->getSession()->create();

            if (!$session->isPositiveOutcome()) {
                return new MethodReply(false, $session->getMessage());
            }
        }
        $functionality->addInstantCooldown(AccountFunctionality::LOG_IN, self::log_in_out_cooldown);
        $this->account->refresh();
        return new MethodReply(true, "Successfully logged in.");
    }

    public function logOut(): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::LOG_OUT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        $session = $this->account->getSession()->delete();

        if ($session->isPositiveOutcome()) {
            $functionality->addInstantCooldown(AccountFunctionality::LOG_OUT, self::log_in_out_cooldown);
        }
        return $session;
    }

    public function deleteAccount(bool $permanently = false): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::DELETE_ACCOUNT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if ($permanently) {
            $tables = get_sql_database_tables("account");

            if (!empty($tables)) {
                $accountID = $this->account->getDetail("id");

                if (delete_sql_query(
                    AccountVariables::ACCOUNTS_TABLE,
                    array(
                        array("id", $accountID)
                    )
                )) {
                    foreach ($tables as $table) {
                        if ($table !== AccountVariables::ACCOUNTS_TABLE) {
                            delete_sql_query($table,
                                array(
                                    array("account_id", $accountID)
                                )
                            );
                        }
                    }
                    return new MethodReply(true, "User successfully deleted permanently.");
                }
                return new MethodReply(false, "Failed to delete account.");
            }
            return new MethodReply(false, "Failed to find database tables.");
        } else {
            if (!set_sql_query(
                AccountVariables::ACCOUNTS_TABLE,
                array("deletion_date" => get_current_date()),
                array(
                    array("id", $this->account->getDetail("id"))
                ),
                null,
                1
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            return new MethodReply(true, "User successfully deleted.");
        }
    }

    public function changeName(string          $name, bool $emailCode = false, string $type = self::NAME,
                               int|string|null $cooldown = "1 day"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_NAME, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->getEmail()->isVerified()) {
            if (!$this->account->getEmail()->initiateVerification(null, $emailCode)->isPositiveOutcome()) {
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
        if (!empty(get_sql_query(
            AccountVariables::ACCOUNTS_TABLE,
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
