<?php

function get_all_stripe_transactions_count(int $limit = 0): int
{
    global $stripe_successful_transactions_table;
    $query = sql_query("SELECT id FROM $stripe_successful_transactions_table LIMIT $limit;");
    return $query != null ? $query->num_rows : 0;
}

function get_all_stripe_transactions(int $limit = 0, bool $details = true, ?string $date = null): array
{
    global $stripe_successful_transactions_table;
    $query = sql_query("SELECT transaction_id"
        . ($details ? ", details" : "")
        . " FROM $stripe_successful_transactions_table"
        . ($date !== null ? " WHERE creation_date >= '" . properly_sql_encode($date) . "'" : "")
        . " ORDER BY id DESC"
        . ($limit > 0 ? " LIMIT " . $limit . ";" : ";"));

    if (isset($query->num_rows) && $query->num_rows > 0) {
        $transactions = array();

        while ($row = $query->fetch_assoc()) {
            $transactions[$row["transaction_id"]] = $details ? json_decode($row["details"]) : true;
        }
        return $transactions;
    } else {
        return array();
    }
}

function get_stripe_transaction(int|string $transactionID)
{
    global $stripe_successful_transactions_table;
    $query = sql_query("SELECT details FROM $stripe_successful_transactions_table WHERE transaction_id = '$transactionID' LIMIT 1;");
    return isset($query->num_rows) && $query->num_rows > 0 ? json_decode($query->fetch_assoc()["details"]) : null;
}

function get_failed_stripe_transactions(?array $findFromArray = null, int $limit = 0, ?string $date = null): array
{
    global $stripe_failed_transactions_table;
    $query = sql_query("SELECT transaction_id FROM $stripe_failed_transactions_table"
        . ($date !== null ? " WHERE creation_date >= '" . properly_sql_encode($date) . "'" : "")
        . " ORDER BY id DESC"
        . ($limit > 0 ? " LIMIT " . $limit . ";" : ";"));

    if (isset($query->num_rows) && $query->num_rows > 0) {
        $hasFindFromArray = $findFromArray !== null;
        $array = array();
        $numericalArray = $hasFindFromArray && isset($findFromArray[0]);

        while ($row = $query->fetch_assoc()) {
            if ($hasFindFromArray) {
                $transactionID = $row["transaction_id"];

                if ($numericalArray ? in_array($transactionID, $findFromArray) : array_key_exists($transactionID, $findFromArray)) {
                    $array[] = $transactionID;
                }
            } else {
                $array[] = $row["transaction_id"];
            }
        }
        return $array;
    }
    return array();
}

function find_stripe_transactions_by_id(int|string $transactionID): array
{
    global $stripe_successful_transactions_table;
    $query = sql_query("SELECT transaction_id, details FROM $stripe_successful_transactions_table WHERE transaction_id = '$transactionID' LIMIT 1;");

    if (isset($query->num_rows) && $query->num_rows > 0) {
        $row = $query->fetch_assoc();
        $transactions = array();
        $transactions[$row["transaction_id"]] = json_decode($row["details"]);
        return $transactions;
    } else {
        return array();
    }
}

function find_stripe_transactions_by_data_pair(array $keyValueArray, int $limit = 0, bool $sqlOnly = false): array
{
    global $stripe_successful_transactions_table;
    $transactions = array();

    if ($sqlOnly) {
        $querySearch = array();

        foreach ($keyValueArray as $key => $value) {
            $querySearch[] = "details LIKE '%\"$key\":\"$value\"%'";
        }
        $query = sql_query("SELECT transaction_id, details FROM $stripe_successful_transactions_table WHERE "
            . implode(" AND ", $querySearch)
            . " ORDER BY id DESC"
            . ($limit > 0 ? " LIMIT " . $limit : "") . ";");

        if (isset($query->num_rows) && $query->num_rows > 0) {
            while ($row = $query->fetch_assoc()) {
                $transactionID = $row["transaction_id"];
                $transactions[$transactionID] = json_decode($row["details"]);
            }
        }
    } else {
        $query = sql_query("SELECT transaction_id, details FROM $stripe_successful_transactions_table"
            . "ORDER BY id DESC"
            . ($limit > 0 ? " LIMIT " . $limit : "") . ";");

        if (isset($query->num_rows) && $query->num_rows > 0) {
            $keyValueArraySize = sizeof($keyValueArray);

            while ($row = $query->fetch_assoc()) {
                $counter = 0;
                $details = json_decode($row["details"]);

                foreach ($keyValueArray as $key => $value) {
                    $key = get_object_depth_key($details, $key);

                    if ($key[0] && $key[1] == $value) {
                        $counter++;
                    }
                }

                if ($counter == $keyValueArraySize) {
                    $transactionID = $row["transaction_id"];
                    $transactions[$transactionID] = $details;
                }
            }
        }
    }
    return $transactions;
}

// Separator

function mark_failed_stripe_transaction(int|string $transactionID, bool $checkExistence = true): bool
{
    global $stripe_failed_transactions_table;
    $process = !$checkExistence;

    if (!$process) {
        $query = sql_query("SELECT id FROM $stripe_failed_transactions_table WHERE transaction_id = '$transactionID';");

        if (isset($query->num_rows) && $query->num_rows > 0) {
            $process = true;
        }
    }
    if ($process) {
        return sql_insert(
                $stripe_failed_transactions_table,
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
    global $stripe_successful_transactions_table;
    $process = !$checkExistence;

    if (!$process) {
        $query = sql_query("SELECT id FROM $stripe_successful_transactions_table WHERE transaction_id = '$transactionID';");

        if (isset($query->num_rows) && $query->num_rows > 0) {
            $process = true;
        }
    }
    if ($process) {
        return sql_insert(
                $stripe_successful_transactions_table,
                array(
                    "transaction_id" => $transactionID,
                    "creation_date" => get_current_date(),
                    "details" => json_encode($transaction)
                )
            ) == true;
    }
    return false;
}
