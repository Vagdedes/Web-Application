<?php

class AccountAccounts
{
    private Account $account;
    public const PAYPAL_EMAIL = 1, STRIPE_EMAIL = 8,
        SPIGOTMC_URL = 5, BUILTBYBIT_URL = 6, POLYMART_URL = 7,
        DISCORD_TAG = 2, PATREON_FULL_NAME = 4, PLATFORM_USERNAME = 3,
        PHONE_NUMBER = 9;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function add($type, $credential, $deletePreviousIfSurpassing = 0): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::ADD_ACCOUNT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->getEmail()->isVerified()) {
            if (!$this->account->getEmail()->initiateVerification()->isPositiveOutcome()) {
                return new MethodReply(false, "You must verify your email first.");
            }
            return new MethodReply(false, "You must verify your email first, an email has been sent to you.");
        }
        $isNumeric = is_numeric($type);
        $acceptedAccount = new AcceptedAccount($this->account->getDetail("application_id"), $isNumeric ? $type : null, $isNumeric ? null : $type);

        if (!$acceptedAccount->exists()) {
            return new MethodReply(false, "This account type does not exist.");
        }
        global $alternate_accounts_table;
        $acceptedAccount = $acceptedAccount->getObject();

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
                    $username = $minecraftPlatform->getUsername();

                    if ($username !== null) {
                        $this->add($this::PLATFORM_USERNAME, $username);
                    }
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
            $alternate_accounts_table,
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
            return new MethodReply(
                false,
                $query[0]->account_id == $accountID ?
                    "You have already added this account."
                    : "Someone else has already added this account."
            );
        }
        $date = get_current_date();

        if (!sql_insert(
            $alternate_accounts_table,
            array(
                "account_id" => $accountID,
                "accepted_account_id" => $type,
                "credential" => $credential,
                "creation_date" => $date
            ))) {
            return new MethodReply(false, "Failed to interact with the database (1).");
        }
        clear_memory(array(self::class), true);

        if (!$this->account->getHistory()->add("add_account", null, $credential)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        $functionality->addUserCooldown(AccountFunctionality::ADD_ACCOUNT, "2 seconds");

        if ($deletePreviousIfSurpassing > 0
            && sizeof($this->getAdded($type, $deletePreviousIfSurpassing + 1)) > $deletePreviousIfSurpassing
            && !set_sql_query(
                $alternate_accounts_table,
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
        return new MethodReply(true, "Successfully stored account.");
    }

    public function remove($type, $idOrCredential = null, $limit = 0): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::REMOVE_ACCOUNT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        $isNumeric = is_numeric($type);
        $acceptedAccount = new AcceptedAccount($this->account->getDetail("application_id"), $isNumeric ? $type : null, $isNumeric ? null : $type);

        if (!$acceptedAccount->exists()) {
            return new MethodReply(false, "This account type does not exist.");
        }
        global $alternate_accounts_table;

        if (!$isNumeric) {
            $type = $acceptedAccount->getObject()->id;
        }
        $hasID = $idOrCredential !== null;
        $isCredential = $hasID && !is_numeric($idOrCredential);

        if (!set_sql_query(
            $alternate_accounts_table,
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
        clear_memory(array(self::class), true);
        $functionality->addUserCooldown(AccountFunctionality::REMOVE_ACCOUNT, "2 seconds");
        return new MethodReply(true, "Successfully deleted account.");
    }

    public function getAdded($id = null, $limit = 0): array
    {
        if (!$this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_ACCOUNTS)->isPositiveOutcome()) {
            return array();
        }
        global $alternate_accounts_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $alternate_accounts_table,
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
                $acceptedAccount = new AcceptedAccount($applicationID, $value->accepted_account_id);

                if ($acceptedAccount->exists()) {
                    $value->accepted_account = $acceptedAccount->getObject();
                    $array[$key] = $value;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }

    public function hasAdded($id, $credential = null, $limit = 0): MethodReply
    {
        $acceptedAccount = new AcceptedAccount($this->account->getDetail("application_id"), $id);

        if (!$acceptedAccount->exists()) {
            return new MethodReply(false);
        }
        global $alternate_accounts_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $alternate_accounts_table,
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
