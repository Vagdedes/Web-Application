<?php

class AccountModerations
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function executeAction($accountID, $moderation, $reason, $duration = null): MethodReply
    {
        if (!is_numeric($moderation)) {
            $moderationObject = new WebsiteModeration($this->account->getDetail("application_id"), $moderation);
            $moderationObject = $moderationObject->getResult();

            if (!$moderationObject->isPositiveOutcome()) {
                return new MethodReply(false, $moderationObject->getMessage());
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        $account = new Account($this->account->getDetail("application_id"), $accountID);

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
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::MODERATE_USER,
            $this->account
        );
        $functionality = $functionality->getResult(true);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        global $executed_moderations_table;

        if (sql_insert(
            $executed_moderations_table,
            array(
                "account_id" => $accountID,
                "executed_by" => $this->account->getDetail("id"),
                "moderation_id" => $moderation,
                "reason" => $reason,
                "creation_date" => get_current_date(),
                "expiration_date" => ($duration ? get_future_date($duration) : null),
            )
        )) {
            clear_memory(array(self::class), true);
            return new MethodReply(true, "Executed moderation action successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function cancelAction($accountID, $moderation, $reason = null): MethodReply
    {
        if (!is_numeric($moderation)) {
            $moderationObject = new WebsiteModeration($this->account->getDetail("application_id"), $moderation);
            $moderationObject = $moderationObject->getResult();

            if (!$moderationObject->isPositiveOutcome()) {
                return new MethodReply(false, $moderationObject->getMessage());
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        $account = new Account($this->account->getDetail("application_id"), $accountID);

        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        if (!$this->account->getPermissions()->hasPermission(array(
            "account.moderation.action.$moderation.cancel",
            "account.moderation.action.*.cancel"
        ), true, $account)) {
            return new MethodReply(false, "You do not have permission to moderate users.");
        }
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::CANCEL_USER_MODERATION,
            $this->account
        );
        $functionality = $functionality->getResult(true);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        global $executed_moderations_table;

        if (set_sql_query(
            $executed_moderations_table,
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
            clear_memory(array(self::class), true);
            return new MethodReply(true, "Cancelled moderation action successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function executeBlockedFunctionality($accountID, $functionality, $reason, $duration = null): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = new WebsiteFunctionality(
                $this->account->getDetail("application_id"),
                $functionality,
                $this->account
            );
            $functionalityObject = $functionalityObject->getResult();

            if (!$functionalityObject->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityObject->getMessage());
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        $account = new Account($this->account->getDetail("application_id"), $accountID);

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
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::BLOCK_FUNCTIONALITY,
            $this->account
        );
        $functionality = $functionality->getResult(true);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        global $blocked_functionalities_table;

        if (sql_insert(
            $blocked_functionalities_table,
            array(
                "account_id" => $accountID,
                "executed_by" => $this->account->getDetail("id"),
                "functionality_id" => $functionality,
                "reason" => $reason,
                "creation_date" => get_current_date(),
                "expiration_date" => ($duration ? get_future_date($duration) : null),
            )
        )) {
            clear_memory(array(self::class), true);
            return new MethodReply(true, "Blocked feature for user successfully.");
        }
        return new MethodReply(false);
    }

    public function restoreBlockedFunctionality($accountID, $functionality, $reason = null): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = new WebsiteFunctionality(
                $this->account->getDetail("application_id"),
                $functionality,
                $this->account
            );
            $functionalityObject = $functionalityObject->getResult();

            if (!$functionalityObject->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityObject->getMessage());
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        $account = new Account($this->account->getDetail("application_id"), $accountID);

        if (!$account->exists()) {
            return new MethodReply(false, "Account does not exist.");
        }
        if (!$this->account->getPermissions()->hasPermission(array(
            "account.moderation.functionality.$functionality.cancel",
            "account.moderation.functionality.*.cancel"
        ), true, $account)) {
            return new MethodReply(false, "You do not have permission to moderate users.");
        }
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::CANCEL_BLOCKED_FUNCTIONALITY,
            $this->account
        );
        $functionality = $functionality->getResult(true);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        global $blocked_functionalities_table;

        if (set_sql_query(
            $blocked_functionalities_table,
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
            clear_memory(array(self::class), true);
            return new MethodReply(true, "Cancelled blocked feature of user successfully.");
        }
        return new MethodReply(false, "Failed to execute moderation action.");
    }

    public function getReceivedAction($moderation, $active = true): MethodReply
    {
        if (!is_numeric($moderation)) {
            $moderationObject = new WebsiteModeration($this->account->getDetail("application_id"), $moderation);
            $moderationObject = $moderationObject->getResult();

            if (!$moderationObject->isPositiveOutcome()) {
                return new MethodReply(false, $moderationObject->getMessage());
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        global $executed_moderations_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $executed_moderations_table,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("moderation_id", $moderation),
                $active ? array("deletion_date", "IS NOT", null) : "",
                $active ? null : "",
                $active ? array("expiration_date", null, 0) : "",
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
            new MethodReply(false) :
            new MethodReply(true, $array[0]["reason"], $array[0]);
    }

    public function hasExecutedAction($moderation, $active = true): bool
    {
        if (!is_numeric($moderation)) {
            $moderationObject = new WebsiteModeration($this->account->getDetail("application_id"), $moderation);
            $moderationObject = $moderationObject->getResult();

            if (!$moderationObject->isPositiveOutcome()) {
                return false;
            } else {
                $moderation = $moderationObject->getObject();
            }
        }
        global $executed_moderations_table;
        set_sql_cache(null, self::class);
        return !empty(get_sql_query(
            $executed_moderations_table,
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

    public function getBlockedFunctionality($functionality, $active = true): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = new WebsiteFunctionality(
                $this->account->getDetail("application_id"),
                $functionality,
                $this->account
            );
            $functionalityObject = $functionalityObject->getResult();

            if (!$functionalityObject->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityObject->getMessage());
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        global $blocked_functionalities_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $blocked_functionalities_table,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("functionality_id", $functionality),
                $active ? array("deletion_date", "IS NOT", null) : "",
                $active ? null : "",
                $active ? array("expiration_date", null, 0) : "",
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
            new MethodReply(false) :
            new MethodReply(true, $array[0]["reason"], $array[0]);
    }

    public function hasExecutedBlockedFunctionality($functionality, $active = true): bool
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = new WebsiteFunctionality(
                $this->account->getDetail("application_id"),
                $functionality,
                $this->account
            );
            $functionalityObject = $functionalityObject->getResult();

            if (!$functionalityObject->isPositiveOutcome()) {
                return false;
            } else {
                $functionality = $functionalityObject->getObject();
            }
        }
        global $blocked_functionalities_table;
        set_sql_cache(null, self::class);
        return !empty(get_sql_query(
            $blocked_functionalities_table,
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

    public function listReceivedActions($active = true): array
    {
        global $executed_moderations_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $executed_moderations_table,
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
            $applicationID = $this->account->getDetail("application_id");

            foreach ($array as $key => $value) {
                $object = new WebsiteModeration($applicationID, $value->moderation_id);
                $object = $object->getResult(array("name"));

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

    public function listExecutedActions($active = true): array
    {
        global $executed_moderations_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $executed_moderations_table,
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
            $applicationID = $this->account->getDetail("application_id");

            foreach ($array as $key => $value) {
                $object = new WebsiteModeration($applicationID, $value->moderation_id);
                $object = $object->getResult(array("name"));

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

    public function listBlockedFunctionalities($active = true): array
    {
        global $blocked_functionalities_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $blocked_functionalities_table,
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
            $applicationID = $this->account->getDetail("application_id");

            foreach ($array as $key => $value) {
                $object = new WebsiteFunctionality($applicationID, $value->functionality_id, $this->account);
                $object = $object->getResult(array("name"));

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

    public function listExecutedBlockedFunctionalities($active = true): array
    {
        global $blocked_functionalities_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $blocked_functionalities_table,
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
            $applicationID = $this->account->getDetail("application_id");

            foreach ($array as $key => $value) {
                $object = new WebsiteFunctionality($applicationID, $value->functionality_id, $this->account);
                $object = $object->getResult(array("name"));

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
