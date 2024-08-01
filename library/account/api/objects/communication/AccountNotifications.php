<?php

class AccountNotifications
{
    private Account $account;

    public const FORM = 1;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getType(int|string $id): MethodReply
    {
        global $account_notification_types_table;
        set_sql_cache();
        $query = get_sql_query(
            $account_notification_types_table,
            array("id", "name", "description", "creation_date"),
            array(
                array("application_id", $this->account->getDetail("application_id")),
                array("id", $id),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($query)) {
            return new MethodReply(true, null, $query[0]);
        } else {
            return new MethodReply(false);
        }
    }

    public function add(int|string $type, string $color, string $information, int|string $duration,
                        bool       $phone = false, bool $email = false, bool $run = false): bool
    {
        if (!$this->account->getFunctionality()->getResult(AccountFunctionality::ADD_NOTIFICATION)->isPositiveOutcome()) {
            return false;
        }
        global $account_notifications_table;

        if (sql_insert($account_notifications_table,
            array(
                "account_id" => $this->account->getDetail("id"),
                "type_id" => $type,
                "color" => $color,
                "information" => $information,
                "phone" => $phone,
                "email" => $email,
                "creation_date" => get_current_date(),
                "expiration_date" => get_future_date($duration),
            )
        )) {
            $this->account->clearMemory(self::class, function ($value) {
                return is_array($value);
            });

            if ($run) {
                $this->get($type, 1, true);
            }
            return true;
        }
        return false;
    }

    public function get(int|string|null $type = null, int $limit = 0, bool $complete = false): array
    {
        if (!$this->account->getFunctionality()->getResult(AccountFunctionality::GET_NOTIFICATION)->isPositiveOutcome()) {
            return array();
        }
        global $account_notifications_table;
        set_sql_cache(self::class);
        $date = get_current_date();
        $query = get_sql_query(
            $account_notifications_table,
            array("id", "color", "type_id", "information", "creation_date", "expiration_date", "email", "phone"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("expiration_date", ">", $date),
                array("completion_date", null),
                $type !== null ? array("type_id", $type) : ""
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
                    if ($row->email !== null) {
                        $this->account->getEmail()->send(
                            "notification",
                            array(
                                "notification" => $row->information
                            )
                        );
                    }
                    if ($row->phone !== null) {
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
                $this->account->clearMemory(self::class, function ($value) {
                    return is_array($value);
                });
            }
        }
        return $query;
    }
}