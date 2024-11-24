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

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->startDate = null;
        $this->endDate = null;
        $this->includeAccount = true;
    }

    public function getType(int|string $id): MethodReply
    {
        $query = get_sql_query(
            AccountVariables::STATISTIC_TYPES_TABLE,
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

    private function getTable(int $type): ?string
    {
        switch ($type) {
            case self::INTEGER:
                return AccountVariables::STATISTIC_INTEGERS_TABLE;
            case self::LONG:
                return AccountVariables::STATISTIC_LONG_TABLE;
            case self::DOUBLE:
                return AccountVariables::STATISTIC_DOUBLE_TABLE;
            case self::STRING:
                return AccountVariables::STATISTIC_STRING_TABLE;
            case self::BOOLEAN:
                return AccountVariables::STATISTIC_BOOLEAN_TABLE;
            default:
                return null;
        }
    }

    private function getWhereArguments(MethodReply $type, int|string $key): array
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

    private function privateGet(string $table, MethodReply $type, int|string $key,
                                int    $limit = 1, $get = null): MethodReply
    {
        if ($type->isPositiveOutcome()) {
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
        } else {
            return new MethodReply(false);
        }
    }

    private function privateSet(string $date, string $table, object $row, mixed $value): MethodReply
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

    private function privateAdd(string     $date, string $table, MethodReply $type,
                                int|string $key, mixed $value, null|int|string $duration = null): MethodReply
    {
        if ($type->isPositiveOutcome()) {
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
        } else {
            return new MethodReply(false);
        }
    }

    // Separator

    public function setDates(string $startDate, string $endDate): void
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function includeAccount(bool $include): void
    {
        $this->includeAccount = $include;
    }

    // Separator

    public function get(int $statisticType, int|string $databaseType, int|string $key): MethodReply
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

    public function set(int        $statisticType, int|string $databaseType,
                        int|string $key, mixed $value, int $limit = 1): MethodReply
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

    public function add(int        $statisticType, int|string $databaseType,
                        int|string $key, mixed $value, int|string $duration): MethodReply
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

    public function delete(int        $statisticType, int|string $databaseType,
                           int|string $key, int $limit = 1): MethodReply
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

    public function permanentlyDelete(int        $statisticType, int|string $databaseType,
                                      int|string $key, int $limit = 1): MethodReply
    {
        $type = $this->getType($databaseType);
        return new MethodReply(
            $type->isPositiveOutcome()
            && delete_sql_query(
                $this->getTable($statisticType),
                $this->getWhereArguments($type, $key),
                $this->getOrderArguments(),
                $limit
            )
        );
    }

    public function exists(int $statisticType, int|string $databaseType, int|string $key): bool
    {
        return $this->privateGet(
            $this->getTable($statisticType),
            $this->getType($databaseType),
            $key,
            1,
            array("id")
        )->isPositiveOutcome();
    }

    public function list(int        $statisticType, int|string $databaseType,
                         int|string $key, int $limit = 1): MethodReply
    {
        return $this->privateGet(
            $this->getTable($statisticType),
            $this->getType($databaseType),
            $key,
            $limit
        );
    }
}