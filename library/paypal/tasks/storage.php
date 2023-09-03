<?php

function update_paypal_storage($startDays, $endDays, $checkFailures)
{
    $processedData = false;

    if ($startDays >= 0 && $endDays > 0 && $endDays > $startDays) {
        global $paypal_successful_transactions_table, $paypal_failed_transactions_table, $paypal_transactions_queue_table;
        $recentDate = date('Y-m-d', strtotime("-$startDays day")) . "T29:59:59Z";
        $pastDate = date('Y-m-d', strtotime("-$endDays day")) . "T00:00:00Z";

        $existingSuccessfulTransactions = array();
        $existingFailedTransactions = array();

        // Separator
        foreach (array(
                     $paypal_successful_transactions_table,
                     $paypal_failed_transactions_table
                 ) as $table) {
            $query = sql_query("SELECT id, transaction_id FROM $table;");

            if ($query != null && $query->num_rows > 0) {
                $array = array();

                while ($row = $query->fetch_assoc()) {
                    $transactionID = $row["transaction_id"];

                    if (!in_array($transactionID, $array)) {
                        $array[] = $transactionID;
                    } else {
                        sql_query("DELETE FROM $table WHERE id = '" . $row["id"] . "';");
                    }
                }

                switch ($table) {
                    case $paypal_successful_transactions_table:
                        $existingSuccessfulTransactions = $array;
                        break;
                    case $paypal_failed_transactions_table:
                        $existingFailedTransactions = $array;
                        break;
                    default:
                        return false;
                }
            }
        }

        // Separator
        $queuedTransactions = array();
        $query = sql_query("SELECT id, transaction_id FROM $paypal_transactions_queue_table WHERE completed IS NULL;");

        if ($query != null && $query->num_rows > 0) {
            while ($row = $query->fetch_assoc()) {
                $transactionID = $row["transaction_id"];

                if (!in_array($transactionID, $existingSuccessfulTransactions)
                    && !in_array($transactionID, $existingFailedTransactions)
                    && !in_array($transactionID, $queuedTransactions)) {
                    $queuedTransactions[$row["id"]] = $transactionID;
                }
            }
        }

        // Separator
        for ($i = 1; $i <= 2; $i++) { // attention
            $businessAccount = $i === 1;
            $personalAccount = $i === 2;
            $oldAccount = $i === 3;

            if ($businessAccount) {
                access_business_paypal_account();
            } else if ($personalAccount) { // Access personal account after business to search for transactions
                access_personal_paypal_account();
            } else if ($oldAccount) { // Access old account after personal to recover transactions
                access_deactivated_personal_paypal_account();
            }

            // Success (Used to contain '&STATUS=Success' but it was removed so all transactions can be first treated as normal)
            $transactions = search_paypal_transactions("CURRENCYCODE=EUR&STARTDATE=$pastDate&ENDDATE=$recentDate&TRANSACTIONCLASS=Received");

            if (is_array($transactions) && !empty($transactions)) {
                foreach ($transactions as $key => $transactionID) {
                    if (strpos($key, "L_TRANSACTIONID") !== false
                        && !in_array($transactionID, $existingSuccessfulTransactions)
                        && !in_array($transactionID, $existingFailedTransactions)
                        && process_successful_paypal_transaction($transactionID)) {
                        $processedData = true;
                    }
                }
            }

            // Reversed
            if ($checkFailures) {
                $recentDate = date('Y-m-d');
                $pastDate = date('Y-m-d', strtotime("-180 days")) . "T00:00:00Z";

                foreach (array("Reversed", "Denied") as $status) {
                    $transactions = search_paypal_transactions("CURRENCYCODE=EUR&STARTDATE=$pastDate&ENDDATE=$recentDate&TRANSACTIONCLASS=Received&STATUS=$status");

                    if (is_array($transactions) && !empty($transactions)) {
                        foreach ($transactions as $key => $transactionID) {
                            if (strpos($key, "L_TRANSACTIONID") !== false
                                && !in_array($transactionID, $existingFailedTransactions)
                                && process_failed_paypal_transaction($transactionID, false)) {
                                $processedData = true;
                            }
                        }
                    }
                }
            }

            // Queue
            if (!empty($queuedTransactions)) {
                foreach ($queuedTransactions as $rowID => $transactionID) {
                    if (process_successful_paypal_transaction($transactionID)) {
                        sql_query("UPDATE $paypal_transactions_queue_table SET completed = '1' WHERE id = '$rowID';");
                        $processedData = true;
                    }
                }
            }

            // Suspended
            $transactions = get_all_paypal_transactions(1000);

            if (!empty($transactions)) {
                $continue = true;

                if (!empty($existingFailedTransactions)) {
                    foreach ($existingFailedTransactions as $failedTransactionID) {
                        unset($transactions[$failedTransactionID]);
                        $continue = false;
                    }
                }
                if ($continue || !empty($transactions)) {
                    $suspendedTransactions = identify_paypal_suspended_transactions($transactions);

                    if (!empty($suspendedTransactions)) {
                        foreach ($suspendedTransactions as $transactionID => $transactionRefundInformation) {
                            $transaction = $transactions[$transactionID];

                            if (isset($transaction->AMT) && isset($transaction->CURRENCYCODE)) {
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
                                        $transactionRefundInformation[0]) === true
                                    && process_failed_paypal_transaction($transactionID, false)) {
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

function process_successful_paypal_transaction($transactionID): bool
{
    $transaction = get_paypal_transaction_details($transactionID);

    if (is_array($transaction)
        && isset($transaction["PAYMENTSTATUS"])
        && isset($transaction["ACK"])
        && $transaction["ACK"] === "Success") {
        global $paypal_successful_transactions_table;

        switch ($transaction["PAYMENTSTATUS"]) {
            case "Completed":
                if (sql_insert(
                    $paypal_successful_transactions_table,
                    array(
                        "transaction_id" => $transactionID,
                        "creation_date" => get_current_date(),
                        "details" => json_encode($transaction)
                    ))) {
                    return true;
                }
                break;
            default:
                if (process_failed_paypal_transaction($transactionID, false)) {
                    return true;
                }
                break;
        }
    }
    return false;
}
