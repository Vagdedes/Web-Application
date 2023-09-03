<?php

class AccountCooldowns
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function add($action, $duration)
    {
        if (!$this->has($action)) {
            global $account_cooldowns_table;
            sql_insert($account_cooldowns_table,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "action_id" => $action,
                    "expiration_date" => get_future_date($duration)
                )
            );
            return true;
        }
        return false;
    }

    public function has($action)
    {
        global $account_cooldowns_table;
        set_sql_cache("1 second");
        return !empty(
                get_sql_query(
                    $account_cooldowns_table,
                    array("id"),
                    array(
                        array("account_id", $this->account->getDetail("id")),
                        array("action_id", $action),
                        array("expiration_date", ">", get_current_date())
                    ),
                    null,
                    1
                )
            );
    }
}
