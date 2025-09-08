<?php

class AccountSettings
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function get(string $option, mixed $default): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $query = get_sql_query(
            AccountVariables::ACCOUNT_SETTINGS_TABLE,
            array("option_value"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("option_key", $option)
            ),
            null,
            1
        );
        return empty($query) ?
            new MethodReply(false, "Setting not found", $default) :
            new MethodReply(true, "Setting found successfully", $query[0]->option_value);
    }

    public function isEnabled(string $option, mixed $default = null): bool
    {
        if (!$this->account->exists()) {
            return false;
        }
        $option = $this->get($option, $default)->getObject();
        return $option !== null && $option !== false;
    }

    public function modify(string $option, mixed $value, int|string|null $cooldown = "2 seconds"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::MODIFY_OPTION, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $date = get_current_date();
        $object = $this->get($option, null);

        if ($object->isPositiveOutcome()) {
            if (!set_sql_query(
                AccountVariables::ACCOUNT_SETTINGS_TABLE,
                array(
                    "option_value" => $value,
                    "last_modification_date" => $date
                ),
                array(
                    array("account_id", $this->account->getDetail("id")),
                    array("option_key", $option)
                ),
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
        } else {
            if (!sql_insert(
                AccountVariables::ACCOUNT_SETTINGS_TABLE,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "option_key" => $option,
                    "option_value" => $value,
                    "creation_date" => $date,
                    "last_modification_date" => $date
                )
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
        }
        if (!$this->account->getHistory()->add(
            "modify_option",
            $object->getObject(),
            $value
        )) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::MODIFY_OPTION, $cooldown);
        }
        return new MethodReply(true, "Option successfully modified.");
    }

    public function toggle(
        string          $option,
        bool            $default = false,
        int|string|null $cooldown = "2 seconds"
    ): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::MODIFY_OPTION, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $date = get_current_date();
        $object = $this->get($option, $default);

        if ($object->isPositiveOutcome()) {
            $oldValue = $object->getObject();
            $enabled = $oldValue === null;
            $value = $enabled ? 1 : null;

            if (!set_sql_query(
                AccountVariables::ACCOUNT_SETTINGS_TABLE,
                array(
                    "option_value" => $value,
                    "last_modification_date" => $date
                ),
                array(
                    array("account_id", $this->account->getDetail("id")),
                    array("option_key", $option)
                ),
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            if (!$this->account->getHistory()->add(
                "toggle_option",
                $oldValue,
                $value
            )) {
                return new MethodReply(false, "Failed to update user history.");
            }
        } else {
            $enabled = true;

            if (!sql_insert(
                AccountVariables::ACCOUNT_SETTINGS_TABLE,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "option_key" => $option,
                    "option_value" => 1,
                    "creation_date" => $date,
                    "last_modification_date" => $date
                )
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            if (!$this->account->getHistory()->add(
                "toggle_option",
                null,
                1
            )) {
                return new MethodReply(false, "Failed to update user history.");
            }
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::MODIFY_OPTION, $cooldown);
        }
        return new MethodReply(
            true,
            "Functionality successfully " . ($enabled ? "enabled." : "disabled."),
            1
        );
    }
}
