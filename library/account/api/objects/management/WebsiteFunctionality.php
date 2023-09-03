<?php

class WebsiteFunctionality
{
    private string $name;
    private ?int $applicationID;
    private ?Account $account;

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
        AUTO_UPDATER = "auto_updater", ADD_NOTIFICATION = "add_notification", GET_NOTIFICATION = "get_notification";

    public function __construct($applicationID, $name, $account = null)
    {
        $this->name = $name;
        $this->applicationID = $applicationID;
        $this->account = $account;
    }

    public function getResult($checkCooldown = false, $select = null): MethodReply
    {
        global $functionalities_table;
        $hasSelect = $select !== null;

        if ($hasSelect && !in_array("id", $select)) {
            $select[] = "id";
        }
        $key = is_numeric($this->name) ? "id" : "name";

        if ($this->applicationID === null) {
            $where = array(
                array($key, $this->name),
                array("deletion_date", null),
                array("application_id", null),
            );
        } else {
            $where = array(
                array($key, $this->name),
                array("deletion_date", null),
                null,
                array("application_id", "IS", null, 0), // Support default functionalities for all applications
                array("application_id", $this->applicationID),
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

            if ($this->account !== null) {
                if ($this->account->getModerations()->getBlockedFunctionality($id)->isPositiveOutcome()) {
                    return new MethodReply(
                        false,
                        "You are blocked from using this functionality.",
                        $id
                    );
                }
                if ($checkCooldown
                    && $this->account->getCooldowns()->has($this->name)) {
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
            "The '" . str_replace("_", "-", $this->name) . "' functionality is disabled or doesn't exist."
        );
    }

    public function addUserCooldown($duration): MethodReply
    {
        if ($this->account !== null) {
            $this->account->getCooldowns()->add($this->name, $duration);
            return new MethodReply(true);
        } else {
            return new MethodReply(false);
        }
    }
}
