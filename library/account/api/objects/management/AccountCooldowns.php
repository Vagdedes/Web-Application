<?php

class AccountCooldowns
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function addInstant($action, $duration): bool
    {
        $action = string_to_integer($action);

        if (!$this->has($action, false)) {
            global $account_instant_cooldowns_table;
            sql_insert($account_instant_cooldowns_table,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "action_id" => $action,
                    "expiration" => strtotime(get_future_date($duration))
                )
            );
            return true;
        }
        return false;
    }

    public function addBuffer($action, $threshold, $duration): bool
    { //todo
        $action = string_to_integer($action);

        if (!$this->has($action, false)) {
            global $account_buffer_cooldowns_table;
            sql_insert($account_buffer_cooldowns_table,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "action_id" => $action,
                    "expiration" => strtotime(get_future_date($duration))
                )
            );
            return true;
        }
        return false;
    }

    public function has($action, $hash = true): bool
    {
        global $account_instant_cooldowns_table;
        set_sql_cache("1 second");
        return !empty(
        get_sql_query(
            $account_instant_cooldowns_table,
            array("id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("action_id", $hash ? string_to_integer($action) : $action),
                array("expiration", ">", time())
            ),
            null,
            1
        )
        );
    }
}
