<?php

class AccountNotifications
{
    private Account $account;

    public const FORM = 1;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function getType()
    {

    }

    public function add($type, $color, $information, $duration): bool
    {
        if (!$this->account->getFunctionality()->getResult(AccountFunctionality::ADD_NOTIFICATION)->isPositiveOutcome()) {
            return false;
        }
        global $account_notifications_table;

        if (sql_insert($account_notifications_table,
            array(
                "account_id" => $this->account->getDetail("id"),
                "color" => $color,
                "type" => $type,
                "information" => $information,
                "creation_date" => get_current_date(),
                "expiration_date" => get_future_date($duration),
            )
        )) {
            clear_memory(array(self::class), true);
            return true;
        }
        return false;
    }

    public function get($type = null, $limit = 0, $complete = false,
                        $email = false, $phoneMessage = false): array
    {
        if (!$this->account->getFunctionality()->getResult(AccountFunctionality::GET_NOTIFICATION)->isPositiveOutcome()) {
            return array();
        }
        global $account_notifications_table;
        set_sql_cache(null, self::class);
        $date = get_current_date();
        $query = get_sql_query(
            $account_notifications_table,
            array("id", "color", "type", "information", "creation_date", "expiration_date"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("expiration_date", ">", $date),
                array("completion_date", null),
                $type !== null ? array("type", $type) : ""
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if ($complete && !empty($query)) {
            $complete = false;

            foreach ($query as $row) {
                if (set_sql_query(
                    $account_notifications_table,
                    array(
                        "completion_date" => $date
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                )) {
                    if ($email) {
                        $this->account->getEmail()->send(
                            "notification",
                            array(
                                "notification" => $row->information
                            )
                        );
                    }
                    if ($phoneMessage) {
                        $this->account->getPhoneNumber()->send(
                            "notification",
                            array(
                                "notification" => $row->information
                            )
                        );
                    }
                    $complete = true;
                }
            }

            if ($complete) {
                clear_memory(array(self::class), true);
            }
        }
        return $query;
    }
}