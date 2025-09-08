<?php

class AccountFunctionality
{
    private Account $account;

    public const
        LOG_IN = 1,
        LOG_OUT = 16,
        REGISTER_ACCOUNT = 2,
        ADD_ACCOUNT = 14,
        REMOVE_ACCOUNT = 19,
        BUY_PRODUCT = 10,
        CHANGE_EMAIL = 4,
        CHANGE_PASSWORD = 3,
        DOWNLOAD_PRODUCT = 12,
        MODERATE_USER = 15,
        MODIFY_OPTION = 9,
        VIEW_PRODUCT = 5,
        RUN_PRODUCT_GIVEAWAY = 17,
        VIEW_PRODUCT_GIVEAWAY = 13,
        USE_COUPON = 6,
        VIEW_HISTORY = 8,
        DELETE_ACCOUNT = 20,
        BLOCK_FUNCTIONALITY = 18,
        CHANGE_NAME = 21,
        CANCEL_BLOCKED_FUNCTIONALITY = 22,
        CANCEL_USER_MODERATION = 23,
        REMOVE_PRODUCT = 25,
        EXCHANGE_PRODUCT = 26,
        ADD_NOTIFICATION = 28,
        GET_NOTIFICATION = 29,
        VIEW_ACCOUNTS = 30;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getAvailable(int $limit = 0): array
    {
        $array = get_sql_query(
            AccountVariables::FUNCTIONALITIES_TABLE,
            array("id", "name"),
            array(
                array("deletion_date", null),
            ),
            null,
            $limit
        );
        $new = array();

        foreach ($array as $object) {
            $new[$object->id] = $object->name;
        }
        return $new;
    }

    public function getResult(int|string $name, bool $checkCooldown = false, ?array $select = null): MethodReply
    {
        $hasSelect = $select !== null;

        if ($hasSelect && !in_array("id", $select)) {
            $select[] = "id";
        }
        $key = is_numeric($name) ? "id" : "name";
        $query = get_sql_query(
            AccountVariables::FUNCTIONALITIES_TABLE,
            $hasSelect ? $select : array("id"),
            array(
                array($key, $name),
                array("deletion_date", null),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $id = $query[0]->id;

            if ($this->account->exists()) {
                if ($this->getReceivedAction($id)->isPositiveOutcome()) {
                    return new MethodReply(
                        false,
                        "You are blocked from using this functionality.",
                        $id
                    );
                }
                if ($checkCooldown
                    && $this->account->getCooldowns()->has($name)) {
                    return new MethodReply(
                        false,
                        "Wait before using this functionality again.",
                        $id
                    );
                }
            }
            return new MethodReply(
                true,
                null,
                $hasSelect ? $query[0] : $id
            );
        }
        return new MethodReply(
            false,
            "The '" . str_replace("_", "-", $name) . "' functionality is disabled or doesn't exist."
        );
    }

    public function addInstantCooldown(string $name, int|string $duration): MethodReply
    {
        if ($this->account->exists()) {
            $this->account->getCooldowns()->addInstant($name, $duration);
            return new MethodReply(true, "Instant cooldown added.");
        } else {
            return new MethodReply(false, "Account does not exist.");
        }
    }

    public function addBufferCooldown(string $name, int|string $threshold, int|string $duration): MethodReply
    {
        if ($this->account->exists()) {
            $this->account->getCooldowns()->addBuffer($name, $threshold, $duration);
            return new MethodReply(true, "Buffer cooldown added.");
        } else {
            return new MethodReply(false, "Account does not exist.");
        }
    }

    public function executeAction(int|string       $accountID, int|string $functionality,
                                  int|float|string $reason, int|string|null $duration = null): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

            if (!$functionalityObject->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityObject->getMessage());
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        $account = $this->account->getNew($accountID);

        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        $hasDuration = $duration !== null;

        if (!$this->account->getPermissions()->hasPermission(array(
            "account.moderation.functionality.$functionality.execute" . ($hasDuration ? "" : ".permanent"),
            "account.moderation.functionality.*.execute" . ($hasDuration ? "" : ".permanent")
        ), true, $account)) {
            return new MethodReply(false, "You do not have permission to moderate users.");
        }
        $functionalityOutcome = $this->getResult(AccountFunctionality::BLOCK_FUNCTIONALITY);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        if (sql_insert(
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            array(
                "account_id" => $accountID,
                "executed_by" => $this->account->getDetail("id"),
                "functionality_id" => $functionality,
                "creation_reason" => $reason,
                "creation_date" => get_current_date(),
                "expiration_date" => ($duration ? get_future_date($duration) : null),
            )
        )) {
            return new MethodReply(true, "Blocked feature for user successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function cancelAction(int|string $accountID, int|string $functionality, string|int|null|float $reason = null): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

            if (!$functionalityObject->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityObject->getMessage());
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        $account = $this->account->getNew($accountID);

        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        if (!$this->account->getPermissions()->hasPermission(array(
            "account.moderation.functionality.$functionality.cancel",
            "account.moderation.functionality.*.cancel"
        ), true, $account)) {
            return new MethodReply(false, "You do not have permission to moderate users.");
        }
        $functionalityResult = $this->getResult(AccountFunctionality::CANCEL_BLOCKED_FUNCTIONALITY);

        if (!$functionalityResult->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityResult->getMessage());
        }
        if (set_sql_query(
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            array(
                "deleted_by" => $this->account->getDetail("id"),
                "deletion_date" => get_current_date(),
                "deletion_reason" => $reason
            ),
            array(
                array("account_id", $accountID),
                array("functionality_id", $functionality),
                array("deletion_date", null),
            ),
            null,
            1
        )) {
            return new MethodReply(true, "Cancelled blocked feature of user successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function getReceivedAction(int|string $functionality, bool $active = true): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

            if (!$functionalityObject->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityObject->getMessage());
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        $array = get_sql_query(
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("functionality_id", $functionality),
                $active ? array("deletion_date", "IS NOT", null) : "",
                $active ? null : "",
                $active ? array("expiration_date", "IS", null, 0) : "",
                $active ? array("expiration_date", ">", get_current_date()) : "",
                $active ? null : ""
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        return empty($array) ?
            new MethodReply(false, ) :
            new MethodReply(true, $array[0]->creation_reason, $array[0]);
    }

    public function hasExecutedAction(int|string $functionality, bool $active = true): bool
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

            if (!$functionalityObject->isPositiveOutcome()) {
                return false;
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        return !empty(get_sql_query(
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            array("id"),
            array(
                array("executed_by", $this->account->getDetail("id")),
                array("functionality_id", $functionality),
                $active ? array("deletion_date", "IS NOT", null) : "",
                $active ? null : "",
                $active ? array("expiration_date", null, 0) : "",
                $active ? array("expiration_date", ">", get_current_date()) : "",
                $active ? null : ""
            ),
            null,
            1
        ));
    }

    public function listReceivedActions(bool $active = true): array
    {
        $array = get_sql_query(
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                $active ? array("deletion_date", "IS NOT", null) : "",
                $active ? null : "",
                $active ? array("expiration_date", null, 0) : "",
                $active ? array("expiration_date", ">", get_current_date()) : "",
                $active ? null : ""
            ),
            array(
                "DESC",
                "id"
            )
        );

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $object = $this->getResult($value->functionality_id);

                if ($object->isPositiveOutcome()) {
                    $value->website_functionality = $object->getObject()->name;
                    $array[$key] = $value;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }

    public function listExecutedActions(bool $active = true): array
    {
        $array = get_sql_query(
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            null,
            array(
                array("executed_by", $this->account->getDetail("id")),
                $active ? array("deletion_date", "IS NOT", null) : "",
                $active ? null : "",
                $active ? array("expiration_date", null, 0) : "",
                $active ? array("expiration_date", ">", get_current_date()) : "",
                $active ? null : ""
            ),
            array(
                "DESC",
                "id"
            )
        );

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $object = $this->getResult($value->functionality_id);

                if ($object->isPositiveOutcome()) {
                    $value->website_functionality = $object->getObject()->name;
                    $array[$key] = $value;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }
}
