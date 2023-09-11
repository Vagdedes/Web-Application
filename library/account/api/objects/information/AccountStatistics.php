<?php

class AccountStatistics
{
    private Account $account;
    private ?string $startDate, $endDate;
    private bool $includeAccount;

    public const
        INTEGER = 0,
        LONG = 1,
        DOUBLE = 2,
        STRING = 3,
        BOOLEAN = 4;

    public function __construct($account)
    {
        $this->account = $account;
        $this->startDate = null;
        $this->endDate = null;
        $this->includeAccount = true;
    }

    public function getType($id): MethodReply
    {
        global $statistic_types_table;
        $query = get_sql_query(
            $statistic_types_table,
            array("id", "name", "description", "creation_date"),
            array(
                array("id", $id),
                array("application_id", $this->account->getDetail("application_id")),
                array("deletion_date", null)
            ),
            null,
            1
        );
        return empty($query)
            ? new MethodReply(false)
            : new MethodReply(true, null, $query[0]);
    }

    private function getTable($type): ?string
    {
        switch ($type) {
            case self::INTEGER:
                global $statistic_integers_table;
                return $statistic_integers_table;
            case self::LONG:
                global $statistic_long_table;
                return $statistic_long_table;
            case self::DOUBLE:
                global $statistic_double_table;
                return $statistic_double_table;
            case self::STRING:
                global $statistic_string_table;
                return $statistic_string_table;
            case self::BOOLEAN:
                global $statistic_boolean_table;
                return $statistic_boolean_table;
            default:
                return null;
        }
    }

    private function getWhereArguments(MethodReply $type, $key): array
    {
        $account = $this->includeAccount
            ? ($this->account->exists() ? array("account_id", $this->account->getDetail("account_id")) : array("account_id", null))
            : "";
        return array(
            array("type", $type->getObject()->id),
            array("deletion_date", null),
            $account,
            $key !== null ? array("statistic_key", $key) : "",
            $this->startDate !== null ? array("modification_date", ">", $this->startDate) : "",
            $this->endDate !== null ? array("modification_date", "<", $this->endDate) : "",
            null,
            array("expiration_date", "IS", null, 0),
            array("expiration_date", ">", get_current_date()),
            null
        );
    }

    private function getOrderArguments(): ?array
    {
        return $this->startDate !== null || $this->endDate !== null
            ? null
            : array(
                "DESC",
                "id"
            );
    }

    // Separator

    private function privateGet($table, MethodReply $type, $key, $limit = 1, $get = null): MethodReply
    {
        $query = get_sql_query(
            $table,
            $get !== null ? $get : array("id", "statistic_key", "statistic_value", "creation_date", "modification_date", "expiration_date"),
            $this->getWhereArguments($type, $key),
            $this->getOrderArguments(),
            $limit
        );

        if (empty($query)) {
            return new MethodReply(false);
        } else {
            return new MethodReply(true, null, $query);
        }
    }

    private function privateSet($date, $table, $row, $value): MethodReply
    {
        return new MethodReply(set_sql_query(
            $table,
            array(
                "statistic_value" => $value,
                "modification_date" => $date
            ),
            array(
                array("id", $row->id),
            ),
            null,
            1
        ));
    }

    private function privateAdd($date, $table, MethodReply $type, $key, $value, $duration = null): MethodReply
    {
        $account = $this->includeAccount && $this->account->exists()
            ? $this->account->getDetail("account_id")
            : null;
        return new MethodReply(sql_insert(
            $table,
            array(
                "account_id" => $account,
                "type" => $type->getObject()->id,
                "statistic_key" => $key,
                "statistic_value" => $value,
                "creation_date" => $date,
                "modification_date" => $date,
                "expiration_date" => $duration !== null ? get_future_date($duration) : null,
            )
        ));
    }

    // Separator

    public function setDates($startDate, $endDate): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function includeAccount($include): void
    {
        $this->includeAccount = $include;
    }

    // Separator

    public function get($statisticType, $databaseType, $key): MethodReply
    {
        $outcome = $this->privateGet(
            $this->getTable($statisticType),
            $this->getType($databaseType),
            $key
        );
        $positive = $outcome->isPositiveOutcome();
        return new MethodReply(
            $positive,
            null,
            $positive ? $outcome->getObject()[0] : null
        );
    }

    public function set($statisticType, $databaseType, $key, $value, $limit = 1): MethodReply
    {
        $date = get_current_date();
        $table = $this->getTable($statisticType);
        $type = $this->getType($databaseType);
        $get = $this->privateGet($table, $type, $key, $limit);

        if ($get->isPositiveOutcome()) {
            $query = $get->getObject();

            if (sizeof($query) === 1) {
                return $this->privateSet($date, $table, $query[0], $value);
            } else {
                $outcome = false;

                foreach ($query as $row) {
                    if ($this->privateSet($date, $table, $row, $value)) {
                        $outcome = true;
                    }
                }
                return new MethodReply($outcome);
            }
        } else {
            return $this->privateAdd($date, $table, $type, $key, $value);
        }
    }

    public function add($statisticType, $databaseType, $key, $value, $duration): MethodReply
    {
        return $this->privateAdd(
            get_current_date(),
            $this->getTable($statisticType),
            $this->getType($databaseType),
            $key,
            $value,
            $duration
        );
    }

    public function delete($statisticType, $databaseType, $key, $limit = 1): MethodReply
    {
        $date = get_current_date();
        $table = $this->getTable($statisticType);
        $get = $this->privateGet($table, $this->getType($databaseType), $key, $limit);

        if ($get->isPositiveOutcome()) {
            $outcome = false;

            foreach ($get->getObject() as $row) {
                if (set_sql_query(
                    $table,
                    array(
                        "deletion_date" => $date
                    ),
                    array(
                        array("id", $row->id),
                    ),
                    null,
                    1
                )) {
                    $outcome = true;
                }
            }
            return new MethodReply($outcome);
        } else {
            return new MethodReply(true); // True because false is meant to signify query failure
        }
    }

    public function permanentlyDelete($statisticType, $databaseType, $key, $limit = 1): MethodReply
    {
        return new MethodReply(delete_sql_query(
            $this->getTable($statisticType),
            $this->getWhereArguments($this->getType($databaseType), $key),
            $this->getOrderArguments(),
            $limit
        ));
    }

    public function exists($statisticType, $databaseType, $key): bool
    {
        return $this->privateGet(
            $this->getTable($statisticType),
            $this->getType($databaseType),
            $key,
            1,
            array("id")
        )->isPositiveOutcome();
    }

    public function list($statisticType, $databaseType, $key, $limit = 1): MethodReply
    {
        return $this->privateGet(
            $this->getTable($statisticType),
            $this->getType($databaseType),
            $key,
            $limit
        );
    }
}