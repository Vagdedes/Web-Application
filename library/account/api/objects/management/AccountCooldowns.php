<?php

class AccountCooldowns
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function addInstant(string $action, int|string $duration): bool
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

    public function addBuffer(string $action, int|string $threshold, int|string $duration): bool
    {
        $action = string_to_integer($action);
        $reply = $this->internalHas($action, false);

        if ($reply->isPositiveOutcome()) {
            $object = $reply->getObject();

            if ($object === null || $object->threshold == $threshold) {
                return true;
            }
            global $account_buffer_cooldowns_table;
            set_sql_query(
                $account_buffer_cooldowns_table,
                array("threshold" => ($threshold + 1)),
                array(
                    array("id", $object->id),
                ),
                null,
                1
            );
        } else {
            global $account_buffer_cooldowns_table;
            sql_insert($account_buffer_cooldowns_table,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "action_id" => $action,
                    "threshold" => 1,
                    "expiration" => strtotime(get_future_date($duration))
                )
            );
            return true;
        }
        return false;
    }

    private function internalHas(string $action, bool $hash = true): MethodReply
    {
        global $account_instant_cooldowns_table, $account_buffer_cooldowns_table;

        if ($hash) {
            $action = string_to_integer($action);
        }
        set_sql_cache("1 second");

        if (!empty(get_sql_query(
            $account_instant_cooldowns_table,
            array("id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("action_id", $action),
                array("expiration", ">", time())
            ),
            null,
            1
        ))) {
            return new MethodReply(true);
        }
        set_sql_cache("1 second");
        $query = get_sql_query(
            $account_buffer_cooldowns_table,
            array("id", "threshold"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("action_id", $action),
                array("expiration", ">", time())
            ),
            null,
            1
        );

        if (!empty($query)) {
            return new MethodReply(true, null, $query[0]);
        }
        return new MethodReply(false);
    }

    public function has(string $action, bool $hash = true): bool
    {
        return $this->internalHas($action, $hash)->isPositiveOutcome();
    }
}
