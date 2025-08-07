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
        if ($this->account->exists()) {
            $action = string_to_integer($action);

            if (!$this->has($action, false, true, false)) {
                sql_insert(AccountVariables::ACCOUNT_INSTANT_COOLDOWNS_TABLE,
                    array(
                        "account_id" => $this->account->getDetail("id"),
                        "action_id" => $action,
                        "expiration" => strtotime(get_future_date($duration))
                    )
                );
                return true;
            }
        }
        return false;
    }

    public function addBuffer(string $action, int|string $threshold, int|string $duration): bool
    {
        if ($this->account->exists()) {
            $action = string_to_integer($action);
            $reply = $this->internalHas($action, false, false);

            if ($reply->isPositiveOutcome()) {
                $object = $reply->getObject();

                if ($object === null || $object->threshold == $threshold) {
                    return true;
                }
                set_sql_query(
                    AccountVariables::ACCOUNT_BUFFER_COOLDOWNS_TABLE,
                    array("threshold" => ($threshold + 1)),
                    array(
                        array("id", $object->id),
                    ),
                    null,
                    1
                );
            } else {
                sql_insert(
                    AccountVariables::ACCOUNT_BUFFER_COOLDOWNS_TABLE,
                    array(
                        "account_id" => $this->account->getDetail("id"),
                        "action_id" => $action,
                        "threshold" => 1,
                        "expiration" => strtotime(get_future_date($duration))
                    )
                );
                return true;
            }
        }
        return false;
    }

    private function internalHas(
        string $action,
        bool   $hash = true,
        bool   $instant = true,
        bool   $buffer = true
    ): MethodReply
    {
        if (!$instant && !$buffer) {
            return new MethodReply(false, "No cooldowns checked.");
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        if ($hash) {
            $action = string_to_integer($action);
        }
        if ($instant
            && !empty(get_sql_query(
                AccountVariables::ACCOUNT_INSTANT_COOLDOWNS_TABLE,
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
        if ($buffer) {
            $query = get_sql_query(
                AccountVariables::ACCOUNT_BUFFER_COOLDOWNS_TABLE,
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
        }
        return new MethodReply(false);
    }

    public function has(
        string $action,
        bool   $hash = true,
        bool   $instant = true,
        bool   $buffer = true
    ): bool
    {
        return $this->internalHas($action, $hash, $instant, $buffer)->isPositiveOutcome();
    }
}
