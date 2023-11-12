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
        global $account_settings_table;
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            $account_settings_table,
            array("option_value"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("option_key", $option)
            ),
            null,
            1
        );
        return empty($query) ?
            new MethodReply(false, null, $default) :
            new MethodReply(true, null, $query[0]->option_value);
    }

    public function isEnabled(string $option, mixed $default = null): bool
    {
        $option = $this->get($option, $default)->getObject();
        return $option !== null && $option !== false;
    }

    public function modify(string $option, mixed $value, int|string $cooldown = "2 seconds"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::MODIFY_OPTION, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $account_settings_table;
        $date = get_current_date();
        $object = $this->get($option, null);

        if ($object->isPositiveOutcome()) {
            if (!set_sql_query(
                $account_settings_table,
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
                $account_settings_table,
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
        $this->account->clearMemory(self::class);
        return new MethodReply(true);
    }

    public function toggle(string $option, int|string $cooldown = "2 seconds"): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::MODIFY_OPTION, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $account_settings_table;
        $date = get_current_date();
        $object = $this->get($option, null);

        if ($object->isPositiveOutcome()) {
            $oldValue = $object->getObject();
            $enabled = $oldValue === null;
            $value = $enabled ? 1 : null;

            if (!set_sql_query(
                $account_settings_table,
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
                $account_settings_table,
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
        $this->account->clearMemory(self::class);
        return new MethodReply(
            true,
            "Functionality successfully " . ($enabled ? "enabled." : "disabled."),
            1
        );
    }
}
