<?php

class AccountActions
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function logIn($password): MethodReply
    {
        $parameter = new ParameterVerification($password, null, 8, 64);

        if (!$parameter->getOutcome()->isPositiveOutcome()) {
            return new MethodReply(false, $parameter->getOutcome()->getMessage());
        }
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::LOG_IN,
            $this->account
        );

        if (!$functionality->getResult(false)->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getResult());
        }
        if (!is_valid_password($password, $this->account->getDetail("password"))) {
            return new MethodReply(false, "Incorrect account password");
        }
        $punishment = $this->account->getModerations()->getReceivedAction(WebsiteModeration::ACCOUNT_BAN);

        if ($punishment->isPositiveOutcome()) {
            return new MethodReply(false, $punishment->getMessage());
        }
        $session = new WebsiteSession($this->account->getDetail("application_id"));

        if (!$session->getSession()->isPositiveOutcome()) {
            $message = $session->getSession()->getMessage();

            if ($message !== null) {
                return new MethodReply(false, $message);
            }
        }
        if ($this->account->getSettings()->isEnabled("two_factor_authentication")) {
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
        return new MethodReply(true);
    }

    public function logOut(): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::LOG_OUT,
            $this->account
        );
        $functionality = $functionality->getResult(true);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        $session = new WebsiteSession($this->account->getDetail("application_id"));
        $session = $session->deleteSession($this->account->getDetail("id"));

        if ($session->isPositiveOutcome()) {
            $session->getObject()->getHistory()->add("log_out");
            return new MethodReply(true, "User logged out successfully.");
        }
        return new MethodReply(false, "Could not find session to log out user.");
    }

    public function isLocallyLoggedIn(): bool
    {
        $session = new WebsiteSession($this->account->getDetail("application_id"));

        if ($session->getSession()->isPositiveOutcome()) {
            $session = $session->getSession()->getObject();
            return $this->account->getDetail("id") == $session->getDetail("id");
        }
        return false;
    }

    public function deleteAccount($permanently = false): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::DELETE_ACCOUNT,
            $this->account
        );
        $functionality = $functionality->getResult(true);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if ($permanently) {
            $tables = get_sql_database_tables("account");

            if (sizeof($tables) > 0) {
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
            return new MethodReply(true, "User successfully deleted.");
        }
    }

    public function changeName($name): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::CHANGE_NAME,
            $this->account
        );
        $functionalityOutcome = $functionality->getResult(true);

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
        if ($name === $this->account->getDetail("name")) {
            return new MethodReply(false, "You already have this name.");
        }
        global $accounts_table;

        if (!empty(get_sql_query(
            $accounts_table,
            array("id"),
            array(
                array("name", $name),
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id"))
            ),
            null,
            1
        ))) {
            return new MethodReply(false, "This name is already taken.");
        }
        if (!set_sql_query(
            $accounts_table,
            array("name" => $name),
            array(
                array("id", $this->account->getDetail("id"))
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        $this->account->setDetail("name", $name);
        $functionality->addUserCooldown("1 day");
        $this->account->getEmail()->send("nameChanged",
            array(
                "newName" => $name
            )
        );
        return new MethodReply(true, "Name successfully changed to '$name'.");
    }
}
