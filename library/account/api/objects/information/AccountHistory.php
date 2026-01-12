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
        if ($this->account->exists()) {
            if (sql_insert(
                AccountVariables::ACCOUNT_HISTORY_TABLE,
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
                return true;
            }
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
                is_array($lastUsed) ? @json_encode($lastUsed) : $lastUsed
            );
    }

    public function get(?array $columns = null, int $limit = 0): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_HISTORY);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        return new MethodReply(
            true,
            null,
            get_sql_query(
                AccountVariables::ACCOUNT_HISTORY_TABLE,
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
