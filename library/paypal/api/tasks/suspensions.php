<?php

function identify_paypal_suspended_transactions(object|array $transactions): array
{
    if (is_array($transactions)) {
        if (!empty($transactions)) {
            global $paypal_suspended_transactions_table;
            $suspendedLicenses = get_sql_query(
                $paypal_suspended_transactions_table,
                array(
                    "id",
                    "transaction_key",
                    "identification_method",
                    "ignore_case",
                    "transaction_value",
                    "transaction_note",
                    "cover_fees"
                ),
                array(
                    array("deletion_date", null),
                ),
                array(
                    "ASC",
                    "id"
                )
            );

            if (!empty($suspendedLicenses)) {
                $array = array();

                foreach ($transactions as $transactionID => $transaction) {
                    if (is_string($transactionID) && is_object($transaction)) {
                        foreach ($suspendedLicenses as $suspendedLicense) {
                            $suspendedTransactionKey = trim($suspendedLicense->transaction_key);

                            if (isset($transaction->{$suspendedTransactionKey})) {
                                $ignoreCase = $suspendedLicense->ignore_case !== null;
                                $suspendedTransactionValue = $ignoreCase ?
                                    strtolower($suspendedLicense->transaction_value) :
                                    $suspendedLicense->transaction_value;
                                $transactionValue = $ignoreCase ?
                                    strtolower($transaction->{$suspendedTransactionKey}) :
                                    $transaction->{$suspendedTransactionKey};
                                $continue = false;

                                switch (trim($suspendedLicense->identification_method)) {
                                    case "startsWith":
                                        if (starts_with($transactionValue, $suspendedTransactionValue)) {
                                            $continue = true;
                                        }
                                        break;
                                    case "endsWith":
                                        if (ends_with($transactionValue, $suspendedTransactionValue)) {
                                            $continue = true;
                                        }
                                        break;
                                    case "equals":
                                        if ($transactionValue == $suspendedTransactionValue) {
                                            $continue = true;
                                        }
                                        break;
                                    case "contains":
                                        if (str_contains($transactionValue, $suspendedTransactionValue)) {
                                            $continue = true;
                                        }
                                        break;
                                    default:
                                        break;
                                }

                                if ($continue) {
                                    $refundNote = $suspendedLicense->transaction_note;

                                    if ($refundNote !== null) {
                                        $id = "#" . $suspendedLicense->id . " ";
                                        $array[$transactionID] = array($id . substr($refundNote, 0, 255 - strlen($id)), $suspendedLicense->cover_fees !== null);
                                    } else {
                                        $array[$transactionID] = array("#" . $suspendedLicense->id, $suspendedLicense->cover_fees !== null);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                return $array;
            }
        }
        return $transactions;
    }
    return identify_paypal_suspended_transactions(array($transactions));
}

function suspend_paypal_transaction(object $transaction, string $reason, bool $coverFees): bool
{
    global $paypal_suspended_transactions_table;
    return sql_insert(
        $paypal_suspended_transactions_table,
        array(
            "transaction_key" => "PAYERID",
            "identification_method" => "equals",
            "transaction_value" => $transaction->PAYERID,
            "transaction_note" => $reason,
            "cover_fees" => $coverFees,
            "creation_date" => get_current_date()
        )
    );
}