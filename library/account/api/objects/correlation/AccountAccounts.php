<?php

class AccountAccounts
{
    private Account $account;
    public const
        PAYPAL_EMAIL = 1,
        STRIPE_EMAIL = 8,
        SPIGOTMC_URL = 5,
        BUILTBYBIT_URL = 6,
        POLYMART_URL = 7,
        PATREON_FULL_NAME = 4,
        PHONE_NUMBER = 9;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getAvailable(?array $select = null, ?int $id = null, bool $manual = true): array
    {
        $acceptedAccount = new AcceptedAccount(
            $this->account->getDetail("application_id"),
            $id,
            null,
            $manual,
            $select
        );
        return $acceptedAccount->getObjects();
    }

    public function add(int|string $type, int|float|string $credential, int $deletePreviousIfSurpassing = 0,
                        bool       $emailCode = false,
                        int|string $cooldown = "2 seconds"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::ADD_ACCOUNT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        if (!$this->account->getEmail()->isVerified()) {
            if (!$this->account->getEmail()->initiateVerification(null, $emailCode)->isPositiveOutcome()) {
                return new MethodReply(false, "You must verify your email first.");
            }
            return new MethodReply(false, "You must verify your email first, an email has been sent to you.");
        }
        $isNumeric = is_numeric($type);
        $acceptedAccount = new AcceptedAccount(
            $this->account->getDetail("application_id"),
            $isNumeric ? $type : null,
            $isNumeric ? null : $type,
            false
        );

        if (!$acceptedAccount->exists()) {
            return new MethodReply(false, "This account type does not exist.");
        }
        $acceptedAccount = $acceptedAccount->getObjects()[0];

        if (!$isNumeric) {
            $type = $acceptedAccount->id;
        }
        switch ($type) {
            case self::SPIGOTMC_URL:
            case self::BUILTBYBIT_URL:
            case self::POLYMART_URL:
                if (!is_numeric($credential)) {
                    $minecraftPlatform = new MinecraftPlatform($credential);

                    if (!$minecraftPlatform->isValid()) {
                        return new MethodReply(false, "This is not a valid Minecraft platform.");
                    }
                    $credential = $minecraftPlatform->getID();
                }
                break;
            case self::STRIPE_EMAIL:
            case self::PAYPAL_EMAIL:
                if (!is_email($credential)) {
                    return new MethodReply(false, "This is not a valid email address.");
                }
                break;
            case self::PHONE_NUMBER:
                if (!is_phone_number($credential)) {
                    return new MethodReply(false, "This is not a valid phone number.");
                }
                break;
            default:
                $parameter = new ParameterVerification($credential, null, 2, 384);

                if (!$parameter->getOutcome()->isPositiveOutcome()) {
                    return new MethodReply(false, $parameter->getOutcome()->getMessage());
                }
                break;
        }
        $deletePreviousIfSurpassing = max(
            $acceptedAccount->limit_before_deletion === null ? 0 : $acceptedAccount->limit_before_deletion,
            $deletePreviousIfSurpassing
        );
        $accountID = $this->account->getDetail("id");
        $query = get_sql_query(
            AccountVariables::ADDED_ACCOUNTS_TABLE,
            array("account_id"),
            array(
                array("credential", $credential),
                array("accepted_account_id", $type),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($query)) {
            if ($query[0]->account_id == $accountID) {
                return new MethodReply(false, "You have already added this account.");
            } else {
                $account = $this->account->getNew($query[0]->account_id);

                if ($account->exists()) {
                    $email = $account->getDetail("email_address");
                    $at = strpos($email, "@");

                    for ($i = 0; $i < max($at / 2, 1); $i++) {
                        $email[$i] = "*";
                    }
                    return new MethodReply(false, "Some else has already added this account with email: " . $email);
                }
            }
        }
        $date = get_current_date();

        if (!sql_insert(
            AccountVariables::ADDED_ACCOUNTS_TABLE,
            array(
                "account_id" => $accountID,
                "accepted_account_id" => $type,
                "credential" => $credential,
                "creation_date" => $date
            ))) {
            return new MethodReply(false, "Failed to interact with the database (1).");
        }

        if (!$this->account->getHistory()->add("add_account", null, $credential)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::ADD_ACCOUNT, $cooldown);
        }

        if ($deletePreviousIfSurpassing > 0
            && sizeof($this->getAdded($type, $deletePreviousIfSurpassing + 1)) > $deletePreviousIfSurpassing
            && !set_sql_query(
                AccountVariables::ADDED_ACCOUNTS_TABLE,
                array("deletion_date" => $date),
                array(
                    array("account_id", $accountID),
                    array("accepted_account_id", $type),
                    array("deletion_date", null)
                ),
                array(
                    "ASC",
                    "id",
                ),
                1
            )) {
            return new MethodReply(false, "Failed to interact with the database (2).");
        }
        clear_patreon_subscription_cache();
        return new MethodReply(true, "Successfully stored account.");
    }

    public function remove(int|string $type, int|float|string $idOrCredential = null,
                           int        $limit = 0, int|string $cooldown = "2 seconds"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::REMOVE_ACCOUNT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $isNumeric = is_numeric($type);
        $acceptedAccount = new AcceptedAccount(
            $this->account->getDetail("application_id"),
            $isNumeric ? $type : null,
            $isNumeric ? null : $type,
            false
        );

        if (!$acceptedAccount->exists()) {
            return new MethodReply(false, "This account type does not exist.");
        }
        if (!$isNumeric) {
            $type = $acceptedAccount->getObjects()[0]->id;
        }
        $hasID = $idOrCredential !== null;
        $isCredential = $hasID && !is_numeric($idOrCredential);

        if (!set_sql_query(
            AccountVariables::ADDED_ACCOUNTS_TABLE,
            array("deletion_date" => get_current_date()),
            array(
                $hasID ? array(($isCredential ? "credential" : "id"), $idOrCredential) : "",
                array("account_id", $this->account->getDetail("id")),
                array("accepted_account_id", $type),
                array("deletion_date", null)
            ),
            array(
                "ASC",
                "id",
            ),
            $hasID ? 0 : $limit
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }

        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::REMOVE_ACCOUNT, $cooldown);
        }
        clear_patreon_subscription_cache();
        return new MethodReply(true, "Successfully deleted account.");
    }

    public function getAdded(int|string|null $id = null, $limit = 0, bool $manual = false): array
    {
        if (!$this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_ACCOUNTS)->isPositiveOutcome()) {
            return array();
        }
        if (!$this->account->exists()) {
            return array();
        }
        $array = get_sql_query(
            AccountVariables::ADDED_ACCOUNTS_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", null),
                $id !== null ? array("accepted_account_id", $id) : ""
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($array)) {
            $applicationID = $this->account->getDetail("application_id");

            foreach ($array as $key => $value) {
                $acceptedAccount = new AcceptedAccount($applicationID, $value->accepted_account_id, null, $manual);

                if ($acceptedAccount->exists()) {
                    $value->accepted_account = $acceptedAccount->getObjects()[0];
                    $array[$key] = $value;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }

    public function hasAdded(int|string            $id,
                             int|float|string|null $credential = null,
                             int                   $limit = 0,
                             bool                  $manual = false): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $acceptedAccount = new AcceptedAccount($this->account->getDetail("application_id"), $id, null, $manual);

        if (!$acceptedAccount->exists()) {
            return new MethodReply(false);
        }
        $array = get_sql_query(
            AccountVariables::ADDED_ACCOUNTS_TABLE,
            array("credential"),
            array(
                array("account_id", $this->account->getDetail("id")),
                $credential !== null ? array("credential", $credential) : "",
                array("deletion_date", null),
                array("accepted_account_id", $id)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (empty($array)) {
            return new MethodReply(false);
        }
        foreach ($array as $key => $value) {
            $array[$key] = $value->credential;
        }
        return new MethodReply(true, null, $array);
    }
}
