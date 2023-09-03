<?php

class AccountRegistry
{

    private MethodReply $outcome;

    public function __construct($applicationID, $email, $password, $name)
    {
        $functionality = new WebsiteFunctionality($applicationID, WebsiteFunctionality::REGISTER_ACCOUNT);

        if (!$functionality->getResult()->isPositiveOutcome()) {
            $this->outcome = new MethodReply(false, $functionality->getResult());
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
        global $accounts_table;

        if (!empty(get_sql_query(
            $accounts_table,
            array("id"),
            array(
                array("name", $name),
                array("deletion_date", null),
                array("application_id", $applicationID)
            ),
            null,
            1
        ))) {
            $this->outcome = new MethodReply(false, "Account with this name already exists.");
            return;
        }
        $account = new Account($applicationID, null, $email);

        if ($account->exists()) {
            $this->outcome = new MethodReply(false, "Account with this email already exists.");
            return;
        }
        if (!sql_insert($accounts_table,
            array(
                "email_address" => $email,
                "password" => encrypt_password($password),
                "name" => $name,
                "creation_date" => get_current_date(),
                "application_id" => $applicationID
            ))) {
            $this->outcome = new MethodReply(false, "Failed to create new account.");
            return;
        } else {
            clear_memory(array(Account::class), true);
        }
        $account = new Account($applicationID, null, $email);

        if (!$account->exists()) {
            $this->outcome = new MethodReply(false, "Failed to find newly created account.");
            return;
        }
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
    }

    public function getOutcome(): MethodReply
    {
        return $this->outcome;
    }
}
