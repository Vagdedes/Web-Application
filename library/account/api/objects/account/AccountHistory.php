<?php

class AccountHistory
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function add(string $action, mixed $oldData = null, mixed $newData = null): bool
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
            $this->account->clearMemory(self::class, function ($value) {
                return is_array($value);
            });
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

    public function get(?array $columns = null, int $limit = 0): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_HISTORY);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        global $account_history_table;
        set_sql_cache(self::class);
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
