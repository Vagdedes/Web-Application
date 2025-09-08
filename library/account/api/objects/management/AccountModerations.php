<?php

class AccountModerations
{
    private Account $account;

    public const ACCOUNT_BAN = "account_ban";

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getAvailable(int $limit = 0): array
    {
        $applicationID = $this->account->getDetail("application_id");

        if ($applicationID === null) {
            $where = array(
                array("deletion_date", null),
                array("application_id", null),
            );
        } else {
            $where = array(
                array("deletion_date", null),
                null,
                array("application_id", "IS", null, 0), // Support default moderations for all applications
                array("application_id", $applicationID),
                null,
            );
        }
        $array = get_sql_query(
            AccountVariables::MODERATIONS_TABLE,
            array("id", "name"),
            $where,
            null,
            $limit
        );
        $new = array();

        foreach ($array as $object) {
            $new[$object->id] = $object->name;
        }
        return $new;
    }

    public function getResult(int|string $name, ?array $select = null): MethodReply
    {
        $hasSelect = $select !== null;
        $key = is_numeric($name) ? "id" : "name";
        $applicationID = $this->account->getDetail("application_id");

        if ($applicationID === null) {
            $where = array(
                array($key, $name),
                array("deletion_date", null),
                array("application_id", null),
            );
        } else {
            $where = array(
                array($key, $name),
                array("deletion_date", null),
                null,
                array("application_id", "IS", null, 0), // Support default moderations for all applications
                array("application_id", $applicationID),
                null,
            );
        }
        $query = get_sql_query(
            AccountVariables::MODERATIONS_TABLE,
            $hasSelect ? $select : array("id"),
            $where,
            null,
            1
        );

        if (!empty($query)) {
            return new MethodReply(
                true,
                null,
                $hasSelect ? $query[0] : $query[0]->id
            );
        } else {
            return new MethodReply(
                false,
                "The '" . str_replace("_", "-", $name) . "' moderation is disabled or doesn't exist."
            );
        }
    }

    public function executeAction(int|string       $accountID, int|string $moderation,
                                  int|string|float $reason,
                                  int|string|null  $duration = null): MethodReply
    {
        if (!is_numeric($moderation)) {
            $moderationObject = $this->getResult($moderation);

            if (!$moderationObject->isPositiveOutcome()) {
                return new MethodReply(false, $moderationObject->getMessage());
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        $account = $this->account->getNew($accountID);

        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        $hasDuration = $duration !== null;

        if (!$this->account->getPermissions()->hasPermission(array(
            "account.moderation.action.$moderation.execute" . ($hasDuration ? "" : ".permanent"),
            "account.moderation.action.*.execute" . ($hasDuration ? "" : ".permanent")
        ), true, $account)) {
            return new MethodReply(false, "You do not have permission to moderate users.");
        }
        if ($accountID === $this->account->getDetail("id")
            && !$this->account->getPermissions()->isAdministrator()) {
            return new MethodReply(false, "You cannot moderate yourself.");
        }
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::MODERATE_USER);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if (sql_insert(
            AccountVariables::EXECUTED_MODERATIONS_TABLE,
            array(
                "account_id" => $accountID,
                "executed_by" => $this->account->getDetail("id"),
                "moderation_id" => $moderation,
                "creation_reason" => $reason,
                "creation_date" => get_current_date(),
                "expiration_date" => ($duration ? get_future_date($duration) : null),
            )
        )) {
            return new MethodReply(true, "Executed moderation action successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function cancelAction(int|string            $accountID, int|string $moderation,
                                 int|string|float|null $reason = null): MethodReply
    {
        if (!is_numeric($moderation)) {
            $moderationObject = $this->getResult($moderation);

            if (!$moderationObject->isPositiveOutcome()) {
                return new MethodReply(false, $moderationObject->getMessage());
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        $account = $this->account->getNew($accountID);

        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        if (!$this->account->getPermissions()->hasPermission(array(
            "account.moderation.action.$moderation.cancel",
            "account.moderation.action.*.cancel"
        ), true, $account)) {
            return new MethodReply(false, "You do not have permission to moderate users.");
        }
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::CANCEL_USER_MODERATION);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if (set_sql_query(
            AccountVariables::EXECUTED_MODERATIONS_TABLE,
            array(
                "deleted_by" => $this->account->getDetail("id"),
                "deletion_date" => get_current_date(),
                "deletion_reason" => $reason
            ),
            array(
                array("account_id", $accountID),
                array("moderation_id", $moderation),
                array("deletion_date", null),
            ),
            null,
            1
        )) {
            return new MethodReply(true, "Cancelled moderation action successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function getReceivedAction(int|string $moderation, bool $active = true): MethodReply
    {
        if (!is_numeric($moderation)) {
            $moderationObject = $this->getResult($moderation);

            if (!$moderationObject->isPositiveOutcome()) {
                return new MethodReply(false, $moderationObject->getMessage());
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        $array = get_sql_query(
            AccountVariables::EXECUTED_MODERATIONS_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("moderation_id", $moderation),
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
            new MethodReply(false, "No moderation action found.") :
            new MethodReply(true, $array[0]->creation_reason, $array[0]);
    }

    public function hasExecutedAction(int|string $moderation, bool $active = true): bool
    {
        if (!is_numeric($moderation)) {
            $moderationObject = $this->getResult($moderation);

            if (!$moderationObject->isPositiveOutcome()) {
                return false;
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        return !empty(get_sql_query(
            AccountVariables::EXECUTED_MODERATIONS_TABLE,
            array("id"),
            array(
                array("executed_by", $this->account->getDetail("id")),
                array("moderation_id", $moderation),
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
            AccountVariables::EXECUTED_MODERATIONS_TABLE,
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
                $object = $this->getResult($value->moderation_id, array("name"));

                if ($object->isPositiveOutcome()) {
                    $value->website_moderation = $object->getObject()->name;
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
            AccountVariables::EXECUTED_MODERATIONS_TABLE,
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
                $object = $this->getResult($value->moderation_id, array("name"));

                if ($object->isPositiveOutcome()) {
                    $value->website_moderation = $object->getObject()->name;
                    $array[$key] = $value;
                } else {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }
}
