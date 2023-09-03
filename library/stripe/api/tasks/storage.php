<?php

function update_stripe_storage(): bool
{
    $stripe = get_stripe_object();

    if ($stripe !== null) {
        $newTransactions = get_stripe_list($stripe->balanceTransactions->all([
            'limit' => 100,
            'expand' => ['data.source.source_transfer.source_transaction']
        ]));

        if (!empty($newTransactions)) {
            $storedTransactions = get_all_stripe_transactions(0, false);
            $failedStoredTransactions = get_failed_stripe_transactions();
            $processed = false;

            foreach ($newTransactions as $transaction) {
                $category = $transaction->reporting_category;

                if ($category == "charge") {
                    $transactionID = $transaction->id;

                    if (!array_key_exists($transactionID, $storedTransactions)) {
                        mark_successful_stripe_transaction($transactionID, $transaction, false);
                        $processed = true;
                    }
                } else if (strpos($category, "refund") !== false
                    || strpos($category, "dispute") !== false) {
                    $transactionID = $transaction->id;

                    if (!array_key_exists($transactionID, $storedTransactions)) {
                        mark_successful_stripe_transaction($transactionID, $transaction, false);
                        $processed = true;
                    }
                    if (!in_array($transactionID, $failedStoredTransactions)) {
                        mark_failed_stripe_transaction($transactionID, false);
                        $processed = true;
                    }
                }
            }
            return $processed;
        }
    }
    return false;
}
