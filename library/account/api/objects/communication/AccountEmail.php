<?php

class AccountEmail
{
    private Account $account;
    private bool $run;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->run = true;
    }

    public function setToRun(bool $run): void
    {
        $this->run = $run;
    }

    public function requestVerification(string          $email, bool $code = false,
                                        int|string|null $cooldown = "1 minute"): MethodReply
    {
        if (!is_email($email)) {
            return new MethodReply(false, "Please enter a valid email address.");
        }
        $email = strtolower($email);
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_EMAIL, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        $currentEmail = $this->account->getDetail("email_address");

        if ($email === strtolower($currentEmail)) {
            return new MethodReply(false, "This is already your email address.");
        }
        if (!empty(get_sql_query(
            AccountVariables::ACCOUNTS_TABLE,
            array("id"),
            array(
                array("email_address", $email),
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id"))
            ),
            null,
            1
        ))) {
            return new MethodReply(false, "This email address process is already in use by another user.");
        }
        if (!$this->account->getHistory()->add("request_email_verification", $currentEmail, $email)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $result = $this->initiateVerification($email, $code);
        $resultOutcome = $result->isPositiveOutcome();

        if ($resultOutcome) {
            if ($cooldown !== null) {
                $functionality->addInstantCooldown(AccountFunctionality::CHANGE_EMAIL, $cooldown);
            }
        }
        return new MethodReply($resultOutcome, $result->getMessage());
    }

    public function completeVerification(?string         $tokenOrCode = null, bool $code = false,
                                         int|string|null $cooldown = "1 day"): MethodReply
    {
        $account = $this->account;
        $exists = $account->exists();

        if ($exists) {
            $functionality = $account->getFunctionality();
            $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_EMAIL);

            if (!$functionalityOutcome->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        $date = get_current_date();
        $array = get_sql_query(
            AccountVariables::EMAIL_VERIFICATIONS_TABLE,
            $exists ? array("id", "email_address") : array("id", "email_address", "account_id"),
            array(
                $tokenOrCode === null
                    ? array("account_id", $account->getDetail("id"))
                    : array($code ? "code" : "token", $tokenOrCode),
                array("completion_date", null),
                array("expiration_date", ">", $date)
            ),
            null,
            1
        );

        if (empty($array)) {
            return new MethodReply(false, "This email verification is invalid or has expired.");
        }
        $object = $array[0];
        $applicationID = $account->getDetail("application_id");

        if (!$exists) {
            $account = $this->account->getNew($object->account_id);

            if (!$account->exists()) {
                return new MethodReply(false, "Failed to find account related to email.");
            }
            $functionality = $account->getFunctionality();
            $functionalityOutcome = $functionality->getResult(AccountFunctionality::CHANGE_EMAIL);

            if (!$functionalityOutcome->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        $email = $object->email_address;

        if ($account->getDetail("email_address") != $email
            && !empty(get_sql_query(
                AccountVariables::ACCOUNTS_TABLE,
                array("id"),
                array(
                    array("email_address", $email),
                    array("deletion_date", null),
                    array("application_id", $applicationID),
                ),
                null,
                1
            ))) {
            return new MethodReply(false, "This email address is already in use by another user.");
        }
        $verifiedEmail = $account->getEmail()->isVerified();

        if (!set_sql_query(
            AccountVariables::EMAIL_VERIFICATIONS_TABLE,
            array("completion_date" => $date),
            array(
                array("id", $object->id),
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        $oldEmail = $account->getDetail("email_address");
        $change = $account->setDetail("email_address", $email);

        if (!$change->isPositiveOutcome()) {
            return new MethodReply(false, $change->getMessage());
        }
        if (!$account->getHistory()->add("complete_email_verification", $oldEmail, $email)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $account->getEmail()->send(
            $verifiedEmail ? "emailChanged" : "emailVerified",
            array(
                "email" => $email,
            ),
            "account",
            false
        );
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::CHANGE_EMAIL, $cooldown);
        }
        return new MethodReply(true, "Your email verification has been successfully completed.");
    }

    public function initiateVerification(?string $email = null, bool $code = false): MethodReply
    {
        if ($email === null) {
            if ($this->isVerified()) {
                return new MethodReply(true, "Your email address is already verified.");
            }
            $email = $this->account->getDetail("email_address");
        } else {
            $parameter = new ParameterVerification($email, ParameterVerification::TYPE_EMAIL, 5, 384);

            if (!$parameter->getOutcome()->isPositiveOutcome()) {
                return new MethodReply(false, $parameter->getOutcome()->getMessage());
            }
            $email = strtolower($email);
        }
        $accountID = $this->account->getDetail("id");
        $date = get_current_date();
        $array = get_sql_query(
            AccountVariables::EMAIL_VERIFICATIONS_TABLE,
            array("token", "code"),
            array(
                array("account_id", $accountID),
                array("completion_date", null),
                array("email_address", $email),
                array("expiration_date", ">", $date)
            ),
            null,
            1
        );

        if (empty($array)) {
            $token = random_string(512);
            $createdCode = $code ? random_string(32) : null;

            if (!sql_insert(
                AccountVariables::EMAIL_VERIFICATIONS_TABLE,
                array(
                    "token" => $token,
                    "code" => $createdCode,
                    "account_id" => $accountID,
                    "email_address" => $email,
                    "creation_date" => $date,
                    "expiration_date" => get_future_date("7 days")
                ))) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
        } else {
            $token = $array[0]->token;
            $createdCode = $array[0]->code;
        }
        $this->send(
            "verifyEmail",
            array(
                "token" => $token,
                "code" => $code ? $createdCode : "(undefined)"
            ),
            "account",
            false
        );
        return new MethodReply(true, "A verification email has been sent to your email.");
    }

    public function isVerified(): bool
    {
        return !empty(get_sql_query(
            AccountVariables::EMAIL_VERIFICATIONS_TABLE,
            array("id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("completion_date", "IS NOT", null),
                null,
                array("email_address", "IS", null, 0), // Support for old system structure
                array("email_address", $this->account->getDetail("email_address")),
                null,
            ),
            null,
            1
        ));
    }

    public function send(string $case, ?array $detailsArray = null,
                         string $type = "account", bool $unsubscribe = true): bool
    {
        $applicationID = $this->account->getDetail("application_id");
        return $this->run
            && $this->account->getSettings()->isEnabled(
                "receive_" . $type . "_emails",
                $type === "account"
            )
            && send_email_by_plan(
                ($applicationID === null ? "" : $applicationID . "-") . $case,
                $this->account->getDetail("email_address"),
                $detailsArray,
                $unsubscribe
            ) === 1;
    }

    public function createTicket(string $subject, string $info, ?string $email = null,
                                 ?array $extra = null): array
    {
        $hasEmail = $email !== null;
        $account = $hasEmail ? $this->account->getNew(null, $email) : $this->account;
        $found = $account->exists();
        $subject = strip_tags($subject);
        $info = strip_tags($info);

        if ($found) {
            if (!$hasEmail) {
                $email = $account->getDetail("email_address");
            }
            $platformsString = "https://www.idealistic.ai/contents/?path=account/panel&platform=0&id="
                . $email . "\r\n\r\n";
            $accounts = $account->getAccounts()->getAdded();

            if (!empty($accounts)) {
                $platformsString .= "Accounts:\r\n";

                foreach ($accounts as $row) {
                    $platformsString .= $row->accepted_account->name . ": " . $row->credential . "\r\n";
                }
                $platformsString .= "\r\n";
            }
            $purchases = $account->getPurchases()->getCurrent();

            if (!empty($purchases)) {
                $platformsString .= "Purchases:\r\n";

                foreach ($purchases as $row) {
                    $products = $this->account->getProduct()->find($row->product_id, false);

                    if ($products->isPositiveOutcome()) {
                        $platformsString .= strip_tags($products->getObject()[0]->name) . "\r\n";
                    }
                }
                $platformsString .= "\r\n";
            }
            if (!empty($extra)) {
                $platformsString .= "Extra:\r\n";

                foreach ($extra as $key => $value) {
                    $platformsString .= $key . ": " . $value . "\r\n";
                }
                $platformsString .= "\r\n";
            }
        } else {
            $platformsString = null;
        }

        while (true) {
            $id = rand(0, 2147483647);

            if (empty(get_sql_query(
                AccountVariables::TICKETS_EMAIL_TABLE,
                array("identifier"),
                array(
                    array("identifier", $id)
                ),
                null,
                1
            ))) {
                sql_insert(
                    AccountVariables::TICKETS_EMAIL_TABLE,
                    array(
                        "identifier" => $id,
                        "account_id" => $found ? $account->getDetail("id") : null,
                        "session_type" => $this->account->getSession()->getType(),
                        "session_key" => $this->account->getSession()->getCustomKey(),
                        "logged_in" => !$hasEmail && $found,
                        "email_address" => $email,
                        "subject" => $subject,
                        "information" => $info,
                        "extra" => $extra !== null ? json_encode($extra) : null,
                        "creation_date" => get_current_date()
                    )
                );
                break;
            }
        }

        $subject = strip_tags($subject);
        $title = get_domain() . " - " . $subject . " [ID: " . $id . "]";
        $content = "ID: " . $id . "\r\n"
            . "Subject: " . $subject . "\r\n"
            . "Email: " . $email . "\r\n"
            . "Type: " . ($hasEmail ? "Logged Out" : "Logged In")
            . "\r\n" . "\r\n"
            . (!empty($platformsString) ? $platformsString : "")
            . strip_tags($info);
        return array($email, $title, $content);
    }
}
