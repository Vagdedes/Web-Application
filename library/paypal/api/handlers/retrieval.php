<?php

function get_all_paypal_transactions_count(): int
{
    global $paypal_successful_transactions_table;
    $query = sql_query("SELECT id FROM $paypal_successful_transactions_table;");
    return $query != null ? $query->num_rows : 0;
}

function get_all_paypal_transactions(int $limit = 0, ?string $date = null): array
{
    global $paypal_successful_transactions_table;
    $transactions = array();
    $query = sql_query("SELECT transaction_id, details FROM $paypal_successful_transactions_table"
        . ($date !== null ? " WHERE creation_date >= '" . properly_sql_encode($date) . "'" : "")
        . " ORDER BY id DESC"
        . ($limit > 0 ? " LIMIT " . $limit . ";" : ";"));

    if (isset($query->num_rows) && $query->num_rows > 0) {
        while ($row = $query->fetch_assoc()) {
            $transactions[$row["transaction_id"]] = json_decode($row["details"]);
        }
    }
    return $transactions;
}

function find_paypal_transactions_by_id(int|string $transactionID): array
{
    global $paypal_successful_transactions_table;
    $query = sql_query("SELECT transaction_id, details FROM $paypal_successful_transactions_table WHERE transaction_id = '$transactionID' LIMIT 1;");

    if (isset($query->num_rows) && $query->num_rows > 0) {
        $row = $query->fetch_assoc();
        $transactions = array();
        $transactions[$row["transaction_id"]] = json_decode($row["details"]);
        return $transactions;
    } else {
        return array();
    }
}

function find_paypal_transactions_by_contains(int|float|bool|string $contains, int $limit = 0): array
{
    global $paypal_successful_transactions_table;
    $transactions = array();
    $query = sql_query("SELECT transaction_id, details FROM $paypal_successful_transactions_table WHERE details LIKE '%$contains%' ORDER BY id DESC" . ($limit > 0 ? " LIMIT " . $limit : "") . ";");

    if (isset($query->num_rows) && $query->num_rows > 0) {
        while ($row = $query->fetch_assoc()) {
            $transactions[$row["transaction_id"]] = json_decode($row["details"]);
        }
    }
    return $transactions;
}

function find_paypal_transactions_by_data_pair(array $keyValueArray, int $limit = 0, bool $sqlOnly = false): array
{
    global $paypal_successful_transactions_table;
    $transactions = array();

    if ($sqlOnly) {
        $querySearch = array();

        foreach ($keyValueArray as $key => $value) {
            $querySearch[] = "details LIKE '%\"$key\":\"$value\"%'";
        }
        $query = sql_query("SELECT transaction_id, details FROM $paypal_successful_transactions_table WHERE "
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
        $query = sql_query("SELECT transaction_id, details FROM $paypal_successful_transactions_table"
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

function queue_paypal_transaction(int|string $transactionID): bool
{
    global $paypal_transactions_queue_table;
    $query = sql_query("SELECT id FROM $paypal_transactions_queue_table WHERE transaction_id = '$transactionID';");

    if (!isset($query->num_rows) || $query->num_rows === 0) {
        return sql_insert(
                $paypal_transactions_queue_table,
                array("transaction_id" => $transactionID),
            ) == true;
    }
    return false;
}

function process_failed_paypal_transaction(int|string $transactionID, bool $checkExistence = true): bool
{
    global $paypal_failed_transactions_table;
    $process = !$checkExistence;

    if (!$process) {
        $query = sql_query("SELECT id FROM $paypal_failed_transactions_table WHERE transaction_id = '$transactionID';");

        if (isset($query->num_rows) && $query->num_rows > 0) {
            $process = true;
        }
    }
    if ($process) {
        return sql_insert($paypal_failed_transactions_table,
                array(
                    "transaction_id" => $transactionID,
                    "creation_date" => get_current_date()
                )) == true;
    }
    return false;
}

// Separator

function get_failed_paypal_transactions(?array $findFromArray = null, int $limit = 0, ?string $date = null): array
{
    global $paypal_failed_transactions_table;
    $query = sql_query("SELECT transaction_id FROM $paypal_failed_transactions_table"
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

                if ($numericalArray
                    ? in_array($transactionID, $findFromArray)
                    : array_key_exists($transactionID, $findFromArray)) {
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
