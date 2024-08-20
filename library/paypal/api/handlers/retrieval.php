<?php

function get_all_paypal_transactions(int $limit = 0, ?string $date = null, bool $details = true): array
{
    global $paypal_successful_transactions_table;
    $transactions = array();
    $query = get_sql_query(
        $paypal_successful_transactions_table,
        $details ? array("transaction_id", "details") : array("transaction_id"),
        $date !== null ? array(array("creation_date", ">=", $date)) : null,
        array(
            "DESC",
            "id"
        ),
        $limit
    );

    if (!empty($query)) {
        foreach ($query as $row) {
            if ($details) {
                $transactions[$row->transaction_id] = json_decode($row->details);
            } else {
                $transactions[] = $row->transaction_id;
            }
        }
    }
    return $transactions;
}

function find_paypal_transactions_by_data_pair(array $keyValueArray, int $limit = 0): array
{
    global $paypal_successful_transactions_table;
    $transactions = array();
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
    return $transactions;
}

// Separator

function queue_paypal_transaction(int|string $transactionID): bool
{
    global $paypal_transactions_queue_table;
    $query = get_sql_query(
        $paypal_transactions_queue_table,
        array("transaction_id"),
        array(
            array("transaction_id", $transactionID)
        ),
        null,
        1
    );

    if (empty($query)) {
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
    return (!$checkExistence
            || empty(get_sql_query(
                $paypal_failed_transactions_table,
                array("transaction_id"),
                array(
                    array("transaction_id", $transactionID)
                ),
                null,
                1
            ))) && sql_insert($paypal_failed_transactions_table,
            array(
                "transaction_id" => $transactionID,
                "creation_date" => get_current_date()
            ));
}

// Separator

function get_failed_paypal_transactions(int $limit = 0, ?string $date = null): array
{
    global $paypal_failed_transactions_table;
    $query = get_sql_query(
        $paypal_failed_transactions_table,
        array("transaction_id"),
        array(
            $date !== null ? array("creation_date", ">=", $date) : "",
            array("deletion_date", null)
        ),
        array(
            "DESC",
            "id"
        ),
        $limit
    );

    if (!empty($query)) {
        foreach ($query as $key => $row) {
            $query[$key] = $row->transaction_id;
        }
    }
    return $query;
}
