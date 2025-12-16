<?php

function update_paypal_storage(
    int  $startDays,
    int  $endDays,
    bool $checkFailures,
    bool $checkSuspended
): bool
{
    $processedData = false;

    if ($startDays >= 0 && $endDays > 0 && $endDays > $startDays) {
        $recentDate = date('Y-m-d', strtotime("-$startDays day")) . "T29:59:59Z";
        $pastDate = date('Y-m-d', strtotime("-$endDays day")) . "T00:00:00Z";

        $existingSuccessfulTransactions = get_all_paypal_transactions(10_000, null, false);
        $existingFailedTransactions = get_failed_paypal_transactions(100_000, get_past_date("181 days"));

        // Completed
        for ($i = 0; $i < 2; $i++) {
            if ($i === 0) {
                access_business_paypal_account();
            } else if ($i === 1) {
                access_personal_paypal_account();
            }

            // Success (Used to contain '&STATUS=Success' but it was removed so all transactions can be first treated as normal)
            foreach (array("Success", "Pending", "Processing") as $status) {
                $transactions = search_paypal_transactions("CURRENCYCODE=EUR&STARTDATE=$pastDate&ENDDATE=$recentDate&TRANSACTIONCLASS=Received&STATUS=$status");

                if (is_array($transactions)
                    && !empty($transactions)) {
                    foreach ($transactions as $key => $transactionID) {
                        if (str_contains($key, "L_TRANSACTIONID")
                            && !in_array($transactionID, $existingSuccessfulTransactions)
                            && !in_array($transactionID, $existingFailedTransactions)
                            && process_successful_paypal_transaction($transactionID)) {
                            $processedData = true;
                            $existingSuccessfulTransactions[] = $transactionID;
                        }
                    }
                }
            }

            // Reversed
            if ($checkFailures) {
                $recentDate = date('Y-m-d');
                $pastDate = date('Y-m-d', strtotime("-180 days")) . "T00:00:00Z";
                $transactions = search_paypal_transactions("CURRENCYCODE=EUR&STARTDATE=$pastDate&ENDDATE=$recentDate&TRANSACTIONCLASS=Received&STATUS=Reversed");

                if (is_array($transactions)
                    && !empty($transactions)) {
                    foreach ($transactions as $key => $transactionID) {
                        if (str_contains($key, "L_TRANSACTIONID")) {
                            if (in_array($transactionID, $existingFailedTransactions)) {
                                if (is_successful_paypal_transaction($transactionID)
                                    && set_sql_query(
                                        PayPalVariables::FAILED_TRANSACTIONS_TABLE,
                                        array("deletion_date" => get_current_date()),
                                        array(
                                            array("transaction_id", $transactionID)
                                        ),
                                        null,
                                        1
                                    )) {
                                    $processedData = true;
                                    $existingSuccessfulTransactions[] = $transactionID;
                                }
                            } else if (process_failed_paypal_transaction($transactionID, false)) {
                                $processedData = true;
                            }
                        }
                    }
                }
            }

            // Queue
            $query = get_sql_query(
                PayPalVariables::TRANSACTIONS_QUEUE_TABLE,
                array(
                    "transaction_id",
                    "id"
                ),
                array(
                    array("completed", null),
                )
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    if (!in_array($row->transaction_id, $existingSuccessfulTransactions)
                        && !in_array($row->transaction_id, $existingFailedTransactions)
                        && process_successful_paypal_transaction($row->transaction_id)
                        && set_sql_query(
                            PayPalVariables::TRANSACTIONS_QUEUE_TABLE,
                            array("completed" => true),
                            array(
                                array("id", $row->id)
                            ),
                            null,
                            1
                        )) {
                        $processedData = true;
                        $existingSuccessfulTransactions[] = $row->transaction_id;
                    }
                }
            }

            // Suspended
            if ($checkSuspended) {
                $transactions = get_all_paypal_transactions(1_000);

                if (!empty($transactions)) {
                    $suspendedTransactions = identify_paypal_suspended_transactions($transactions);

                    if (!empty($suspendedTransactions)) {
                        foreach ($suspendedTransactions as $transactionID => $transactionRefundInformation) {
                            $transaction = $transactions[$transactionID];

                            if (isset($transaction->AMT)
                                && isset($transaction->CURRENCYCODE)
                                && !in_array($transactionID, $existingFailedTransactions)) {
                                $partialRefund = $transactionRefundInformation[1] !== true;
                                $refundAmount = $transaction->AMT;

                                if ($partialRefund) { // Refund Fees
                                    $refundAmount -= $transaction->FEEAMT ?? 0;
                                }

                                if ($refundAmount > 0.0
                                    && refund_paypal_transaction(
                                        $transactionID,
                                        $partialRefund,
                                        $refundAmount,
                                        $transaction->CURRENCYCODE,
                                        $transactionRefundInformation[0]
                                    ) === true
                                    && process_failed_paypal_transaction(
                                        $transactionID,
                                        false
                                    )) {
                                    $processedData = true;
                                }
                            }
                        }
                    }
                }
            }

            // End
        }
    }
    return $processedData;
}

function is_successful_paypal_transaction(int|string $transaction): bool
{
    $transaction = get_paypal_transaction_details($transaction);
    if (is_array($transaction)
        && array_key_exists("PAYMENTSTATUS", $transaction)
        && array_key_exists("ACK", $transaction)
        && $transaction["ACK"] === "Success") {
        switch ($transaction["PAYMENTSTATUS"]) {
            case "Completed":
            case "Pending":
            case "Processing":
                return true;
            default:
                break;
        }
    }
    return false;
}

function process_successful_paypal_transaction(int|string $transactionID): bool
{
    $transaction = get_paypal_transaction_details($transactionID);

    if (is_array($transaction)
        && array_key_exists("PAYMENTSTATUS", $transaction)
        && array_key_exists("ACK", $transaction)
        && $transaction["ACK"] === "Success") {
        switch ($transaction["PAYMENTSTATUS"]) {
            case "Completed":
            case "Pending":
            case "Processing":
                if (sql_insert(
                    PayPalVariables::SUCCESSFUL_TRANSACTIONS_TABLE,
                    array(
                        "transaction_id" => $transactionID,
                        "creation_date" => get_current_date(),
                        "details" => json_encode($transaction)
                    ))) {
                    return true;
                }
                break;
            default:
                return process_failed_paypal_transaction($transactionID, false);
        }
    }
    return false;
}
