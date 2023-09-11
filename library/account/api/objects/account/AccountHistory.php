<?php

class AccountHistory
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function add($action, $oldData = null, $newData = null): bool
    {
        global $account_history_table;

        if (sql_insert($account_history_table,
            array(
                "account_id" => $this->account->getDetail("id"),
                "action_id" => $action,
                "ip_address" => get_client_ip_address(),
                "user_agent" => get_user_agent(),
                "creation_date" => get_current_date(),
                "old_data" => $oldData,
                "new_data" => $newData
            )
        )) {
            $this->account->clearMemory(self::class);
            return true;
        }
        return false;
    }

    public function addLastUsedPermission(): bool
    {
        $lastUsed = $this->account->getPermissions()->getLastUsedPermission();
        return $lastUsed !== null
            && $this->add(
                "staff_intervention",
                null,
                is_array($lastUsed) ? json_encode($lastUsed) : $lastUsed
            );
    }

    public function get($columns = null, $limit = 0): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_HISTORY);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        global $account_history_table;
        set_sql_cache(null, self::class);
        return new MethodReply(
            true,
            null,
            get_sql_query($account_history_table,
                $columns,
                array(
                    array("account_id", $this->account->getDetail("id"))
                ),
                array(
                    "DESC",
                    "id"
                ),
                $limit
            )
        );
    }
}
