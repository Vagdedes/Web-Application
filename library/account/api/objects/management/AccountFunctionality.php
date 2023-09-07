<?php

class AccountFunctionality
{
    private Account $account;

    public const LOG_IN = "log_in", LOG_OUT = "log_out", REGISTER_ACCOUNT = "register_account",
        ADD_ACCOUNT = "add_account", REMOVE_ACCOUNT = "remove_account", BUY_PRODUCT = "buy_product",
        CHANGE_EMAIL = "change_email", CHANGE_PASSWORD = "change_password", DOWNLOAD_PRODUCT = "download_product",
        MODERATE_USER = "moderate_user", MODIFY_OPTION = "modify_option", VIEW_PRODUCT = "view_product",
        RUN_PRODUCT_GIVEAWAY = "run_product_giveaway", VIEW_PRODUCT_GIVEAWAY = "view_product_giveaway",
        USE_COUPON = "use_coupon", VIEW_HISTORY = "view_history", VIEW_OFFER = "view_offer",
        DELETE_ACCOUNT = "delete_account", BLOCK_FUNCTIONALITY = "block_functionality",
        CHANGE_NAME = "change_name", CANCEL_BLOCKED_FUNCTIONALITY = "cancel_blocked_functionality",
        CANCEL_USER_MODERATION = "cancel_user_moderation", COMPLETE_EMAIL_VERIFICATION = "complete_email_verification",
        REMOVE_PRODUCT = "remove_product", EXCHANGE_PRODUCT = "exchange_product",
        AUTO_UPDATER = "auto_updater", ADD_NOTIFICATION = "add_notification", GET_NOTIFICATION = "get_notification",
        VIEW_ACCOUNTS = "view_accounts", COMPLETE_CHANGE_PASSWORD = "complete_change_password";

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function getAvailable(): array
    {
        global $functionalities_table;
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
        set_sql_cache("1 minute");
        $array = get_sql_query(
            $functionalities_table,
            array("name"),
            $where
        );

        foreach ($array as $key => $object) {
            $array[$key] = $object->name;
        }
        return $array;
    }

    public function getResult($name, $checkCooldown = false, $select = null): MethodReply
    {
        global $functionalities_table;
        $hasSelect = $select !== null;

        if ($hasSelect && !in_array("id", $select)) {
            $select[] = "id";
        }
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
                array("application_id", "IS", null, 0), // Support default functionalities for all applications
                array("application_id", $applicationID),
                null,
            );
        }
        set_sql_cache("1 minute");
        $query = get_sql_query(
            $functionalities_table,
            $hasSelect ? $select : array("id"),
            $where,
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

    public function addInstantCooldown($name, $duration): MethodReply
    {
        if ($this->account->exists()) {
            $this->account->getCooldowns()->addInstant($name, $duration);
            return new MethodReply(true);
        } else {
            return new MethodReply(false);
        }
    }

    public function addBufferCooldown($name, $threshold, $duration): MethodReply
    {
        if ($this->account->exists()) {
            $this->account->getCooldowns()->addBuffer($name, $threshold, $duration);
            return new MethodReply(true);
        } else {
            return new MethodReply(false);
        }
    }

    public function executeAction($accountID, $functionality, $reason, $duration = null): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

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
        $functionality = $this->getResult(AccountFunctionality::BLOCK_FUNCTIONALITY);

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
                "creation_reason" => $reason,
                "creation_date" => get_current_date(),
                "expiration_date" => ($duration ? get_future_date($duration) : null),
            )
        )) {
            clear_memory(array(self::class), true);
            return new MethodReply(true, "Blocked feature for user successfully.");
        }
        return new MethodReply(false);
    }

    public function cancelAction($accountID, $functionality, $reason = null): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

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
        $functionality = $this->getResult(AccountFunctionality::CANCEL_BLOCKED_FUNCTIONALITY);

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

    public function getReceivedAction($functionality, $active = true): MethodReply
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

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
            new MethodReply(true, $array[0]["creation_reason"], $array[0]);
    }

    public function hasExecutedAction($functionality, $active = true): bool
    {
        if (!is_numeric($functionality)) {
            $functionalityObject = $this->getResult($functionality);

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
            foreach ($array as $key => $value) {
                $object = $this->getResult(array("name"));

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

    public function listExecutedActions($active = true): array
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
            foreach ($array as $key => $value) {
                $object = $this->getResult(array("name"));

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
