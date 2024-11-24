<?php

function get_all_stripe_transactions_count(int $limit = 0): int
{
    return sizeof(get_sql_query(
        StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
        array("id"),
        null,
        array(
            "DESC",
            "id"
        ),
        $limit
    ));
}

function get_all_stripe_transactions(int $limit = 0, bool $details = true, ?string $date = null): array
{
    $query = get_sql_query(
        StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
        $details ? array("transaction_id", "details") : array("transaction_id"),
        $date !== null ? array(array("creation_date", ">=", $date)) : null,
        array(
            "DESC",
            "id"
        ),
        $limit
    );

    if (!empty($query)) {
        $transactions = array();

        foreach ($query as $row) {
            $transactions[$row->transaction_id] = $details ? json_decode($row->details) : true;
        }
        return $transactions;
    } else {
        return array();
    }
}

function get_stripe_transaction(int|string $transactionID)
{
    $query = get_sql_query(
        StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
        array("details"),
        array(
            array("transaction_id", $transactionID)
        ),
        null,
        1
    );
    return !empty($query) ? json_decode($query[0]->details) : null;
}

function get_failed_stripe_transactions(?array $findFromArray = null, int $limit = 0, ?string $date = null): array
{
    $query = get_sql_query(
        StripeVariables::FAILED_TRANSACTIONS_TABLE,
        array("transaction_id"),
        $date !== null ? array(array("creation_date", ">=", $date)) : null,
        array(
            "DESC",
            "id"
        ),
        $limit
    );

    if (!empty($query)) {
        $hasFindFromArray = $findFromArray !== null;
        $array = array();
        $numericalArray = $hasFindFromArray && isset($findFromArray[0]);

        foreach ($query as $row) {
            if ($hasFindFromArray) {
                $transactionID = $row->transaction_id;

                if ($numericalArray
                    ? in_array($transactionID, $findFromArray)
                    : array_key_exists($transactionID, $findFromArray)) {
                    $array[] = $transactionID;
                }
            } else {
                $array[] = $row->transaction_id;
            }
        }
        return $array;
    }
    return array();
}

function find_stripe_transactions_by_id(int|string $transactionID): array
{
    $query = get_sql_query(
        StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
        array("transaction_id", "details"),
        array(
            array("transaction_id", $transactionID)
        ),
        null,
        1
    );

    if (!empty($query)) {
        $query = $query[0];
        $transactions = array();
        $transactions[$query->transaction_id] = json_decode($query->details);
        return $transactions;
    } else {
        return array();
    }
}

function find_stripe_transactions_by_data_pair(array $keyValueArray, int $limit = 0, bool $sqlOnly = false): array
{
    $transactions = array();

    if ($sqlOnly) {
        $querySearch = array();

        foreach ($keyValueArray as $key => $value) {
            $querySearch[] = "details LIKE '%\"$key\":\"$value\"%'";
        }
        $query = get_sql_query(
            StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
            array("transaction_id", "details"),
            null,
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $transactionID = $row->transaction_id;
                $transactions[$transactionID] = json_decode($row->details);
            }
        }
    } else {
        $query = get_sql_query(
            StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
            array("transaction_id", "details"),
            null,
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($query)) {
            $keyValueArraySize = sizeof($keyValueArray);

            foreach ($query as $row) {
                $counter = 0;
                $details = json_decode($row->details);

                foreach ($keyValueArray as $key => $value) {
                    $key = get_object_depth_key($details, $key);

                    if ($key[0] && $key[1] == $value) {
                        $counter++;
                    }
                }

                if ($counter == $keyValueArraySize) {
                    $transactions[$row->transaction_id] = $details;
                }
            }
        }
    }
    return $transactions;
}

// Separator

function mark_failed_stripe_transaction(int|string $transactionID, bool $checkExistence = true): bool
{
    $process = !$checkExistence;

    if (!$process) {
        $query = get_sql_query(
            StripeVariables::FAILED_TRANSACTIONS_TABLE,
            array("transaction_id"),
            array(
                array("transaction_id", $transactionID)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $process = true;
        }
    }
    if ($process) {
        return sql_insert(
                StripeVariables::FAILED_TRANSACTIONS_TABLE,
                array(
                    "transaction_id" => $transactionID,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function mark_successful_stripe_transaction(int|string $transactionID, object $transaction,
                                            bool       $checkExistence = true): bool
{
    $process = !$checkExistence;

    if (!$process) {
        $query = get_sql_query(
            StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
            array("transaction_id"),
            array(
                array("transaction_id", $transactionID)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $process = true;
        }
    }
    if ($process) {
        return sql_insert(
                StripeVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
                array(
                    "transaction_id" => $transactionID,
                    "creation_date" => get_current_date(),
                    "details" => json_encode($transaction)
                )
            ) == true;
    }
    return false;
}
