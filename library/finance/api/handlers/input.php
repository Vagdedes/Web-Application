<?php

function get_financial_input(int|string $year, int|string $month, $standardTax = 1.24): array
{
    global $backup_domain;
    $monthString = ($month < 10 ? "0" . $month : $month);
    $previousMonth = $monthString - 1;
    $potentialPreviousYear = $year;

    if ($previousMonth == 0) {
        $previousMonth = 12;
        $potentialPreviousYear = $year - 1;
    } else if ($previousMonth < 10) {
        $previousMonth = "0" . $previousMonth;
    }
    $currentMonthDays = cal_days_in_month(CAL_GREGORIAN, $monthString, $year);
    $previousMonthDays = cal_days_in_month(CAL_GREGORIAN, $previousMonth, $potentialPreviousYear);
    $startDate = $potentialPreviousYear . "-" . $previousMonth . "-" . $previousMonthDays . " 22:00:00"; // GMT+2
    $endDate = $year . "-" . $monthString . "-" . $currentMonthDays . " 21:59:59"; // GMT +2

    // Separator
    $results = array();

    // Separator
    $blacklist = get_sql_query(
        "personal.expensesBlacklist",
        array("transaction_key", "transaction_value"),
        array(
            array("deletion_date", null)
        )
    );

    // Separator
    $totalString = "total";
    $transactions = get_all_paypal_transactions(0, $startDate);

    if (!empty($transactions)) {
        $failedTransactions = get_failed_paypal_transactions(0, $startDate);

        foreach ($transactions as $transactionID => $transaction) {
            foreach ($blacklist as $blacklisted) {
                if (isset($transaction->{$blacklisted->transaction_key})
                    && $transaction->{$blacklisted->transaction_key} == $blacklisted->transaction_value) {
                    continue 2;
                }
            }
            $date = str_replace("T", " ", str_replace("Z", "", $transaction->ORDERTIME));

            if ($date >= $startDate && $date <= $endDate) {
                $fee = isset($transaction->FEEAMT) ? abs($transaction->FEEAMT) : 0.0;
                $amount = $transaction->AMT;
                $beforeTax = $amount - $fee;
                $currency = $transaction->CURRENCYCODE;
                $foundEmail = isset($transaction->RECEIVERBUSINESS);
                $receivers = array(
                    $totalString,
                    $foundEmail ? "paypal:" . $transaction->RECEIVERBUSINESS : "Unknown"
                );

                $object = new stdClass();
                $object->date = $date;
                $object->amount = $beforeTax . " " . $currency;
                $object->name = $transaction->FIRSTNAME . " " . $transaction->LASTNAME;
                $object->email = $transaction->EMAIL;
                $object->details = $backup_domain . "/contents/?path=finance/paypal/view&id=" . $transactionID . "&domain=" . get_domain();
                $object->country = code_to_country($transaction->COUNTRYCODE);

                if (!in_array($transactionID, $failedTransactions)) {
                    foreach ($receivers as $receiver) {
                        if (!array_key_exists($receiver, $results)) {
                            $resultObject = new stdClass();
                            $resultObject->profit_before_tax = $beforeTax;
                            $resultObject->profit_after_tax = $beforeTax;
                            $resultObject->fees = $fee;
                            $resultObject->tax = 0.0;
                            $resultObject->loss = 0.0;

                            if ($receiver != $totalString) {
                                $array = array();
                                $array[strtotime($date)] = $object;
                                $resultObject->succesful_transactions = $array;
                                $resultObject->failed_transactions = array();
                            }
                            $results[$receiver] = $resultObject;
                        } else {
                            $resultObject = $results[$receiver];
                            $resultObject->profit_before_tax += $beforeTax;
                            $resultObject->profit_after_tax += $beforeTax;
                            $resultObject->fees += $fee;

                            if ($receiver != $totalString) {
                                $resultObject->succesful_transactions[strtotime($date)] = $object;
                                ksort($resultObject->succesful_transactions);
                            }
                        }
                    }
                } else {
                    foreach ($receivers as $receiver) {
                        if (!array_key_exists($receiver, $results)) {
                            $resultObject = new stdClass();
                            $resultObject->profit_before_tax = 0;
                            $resultObject->profit_after_tax = 0;
                            $resultObject->fees = 0;
                            $resultObject->tax = 0;
                            $resultObject->loss = $beforeTax;

                            if ($receiver != $totalString) {
                                $array = array();
                                $array[strtotime($date)] = $object;
                                $resultObject->succesful_transactions = array();
                                $resultObject->failed_transactions = $array;
                            }
                            $results[$receiver] = $resultObject;
                        } else {
                            $resultObject = $results[$receiver];
                            $resultObject->loss += $beforeTax;

                            if ($receiver != $totalString) {
                                $resultObject->failed_transactions[strtotime($date)] = $object;
                                ksort($resultObject->failed_transactions);
                            }
                        }
                    }
                }
            }
        }
    }

    // Separator
    $transactions = get_all_stripe_transactions(0, true, $startDate);

    if (!empty($transactions)) {
        $failedTransactions = get_failed_stripe_transactions(null, 0, $startDate);

        foreach ($transactions as $transactionID => $transaction) {
            foreach ($blacklist as $blacklisted) {
                if (isset($transaction->{$blacklisted->transaction_key})
                    && $transaction->{$blacklisted->transaction_key} == $blacklisted->transaction_value) {
                    continue 2;
                }
            }
            $date = date("Y-m-d H:i:s", $transaction->created);

            if ($date >= $startDate && $date <= $endDate) {
                $fee = isset($transaction->fee) ? $transaction->fee / 100.0 : 0.0;
                $amount = $transaction->amount / 100.0;
                $beforeTax = $amount - $fee;
                $currency = strtoupper($transaction->currency);
                $receivers = array(
                    $totalString,
                    "stripe"
                );

                $object = new stdClass();
                $object->date = $date;
                $object->name = get_object_depth_key($transaction, "source.billing_details.name")[1];
                $object->email = get_object_depth_key($transaction, "source.billing_details.email")[1];
                $object->amount = $beforeTax . " " . $currency;
                $object->details = $backup_domain . "/contents/?path=finance/stripe/view&id=" . $transactionID . "&domain=" . get_domain();

                if (!in_array($transactionID, $failedTransactions)) {
                    foreach ($receivers as $receiver) {
                        if (!array_key_exists($receiver, $results)) {
                            $resultObject = new stdClass();
                            $resultObject->profit_before_tax = $beforeTax;
                            $resultObject->profit_after_tax = $beforeTax;
                            $resultObject->fees = $fee;
                            $resultObject->tax = 0.0;
                            $resultObject->loss = 0.0;

                            if ($receiver != $totalString) {
                                $array = array();
                                $array[strtotime($date)] = $object;
                                $resultObject->succesful_transactions = $array;
                                $resultObject->failed_transactions = array();
                            }
                            $results[$receiver] = $resultObject;
                        } else {
                            $resultObject = $results[$receiver];
                            $resultObject->profit_before_tax += $beforeTax;
                            $resultObject->profit_after_tax += $beforeTax;
                            $resultObject->fees += $fee;

                            if ($receiver != $totalString) {
                                $resultObject->succesful_transactions[strtotime($date)] = $object;
                                ksort($resultObject->succesful_transactions);
                            }
                        }
                    }
                } else {
                    foreach ($receivers as $receiver) {
                        if (!array_key_exists($receiver, $results)) {
                            $resultObject = new stdClass();
                            $resultObject->profit_before_tax = 0.0;
                            $resultObject->profit_after_tax = 0.0;
                            $resultObject->fees = 0.0;
                            $resultObject->tax = 0.0;
                            $resultObject->loss = $beforeTax;

                            if ($receiver != $totalString) {
                                $array = array();
                                $array[strtotime($date)] = $object;
                                $resultObject->succesful_transactions = array();
                                $resultObject->failed_transactions = $array;
                            }
                            $results[$receiver] = $resultObject;
                        } else {
                            $resultObject = $results[$receiver];
                            $resultObject->loss += $beforeTax;

                            if ($receiver != $totalString) {
                                $resultObject->failed_transactions[strtotime($date)] = $object;
                                ksort($resultObject->failed_transactions);
                            }
                        }
                    }
                }
            }
        }
    }

    // Separator
    $account = new Account();
    $products = $account->getProduct()->find(null, false);

    if ($products->isPositiveOutcome()) {
        $receivers = array(
            $totalString,
            "builtbybit"
        );
        $feePercentage = 0.05;
        $currency = "USD";
        $identification = array();
        $redundantDates = array();

        foreach ($products->getObject() as $product) {
            if (!$product->is_free
                && array_key_exists(AccountAccounts::SPIGOTMC_URL, $product->identification)) {
                $bbbProductID = $product->identification[AccountAccounts::BUILTBYBIT_URL]->accepted_account_product_id;

                if (!in_array($bbbProductID, $identification)) {
                    $identification[] = $bbbProductID;
                    $ownerships = get_builtbybit_resource_ownerships($bbbProductID);
                    $amount = array_shift($product->tiers->paid)?->price;

                    if ($amount !== null) {
                        $fee = $amount * $feePercentage;
                        $beforeTax = $amount - $fee;

                        foreach ($ownerships as $ownership) {
                            $date = $ownership->creation_date;
                            $object = new stdClass();
                            $object->user = $ownership->user;
                            $object->date = $date;
                            $object->amount = $beforeTax . " " . $currency;
                            $object->details = $ownership->transaction_id;

                            if ($ownership->creation_date >= $startDate
                                && $ownership->creation_date <= $endDate
                                && !in_array($date, $redundantDates)) {
                                $redundantDates[] = $date;

                                foreach ($receivers as $receiver) {
                                    if (!array_key_exists($receiver, $results)) {
                                        $resultObject = new stdClass();
                                        $resultObject->profit_before_tax = $beforeTax;
                                        $resultObject->profit_after_tax = $beforeTax;
                                        $resultObject->fees = $fee;
                                        $resultObject->tax = 0.0;

                                        if ($receiver != $totalString) {
                                            $array = array();
                                            $array[strtotime($date)] = $object;
                                            $resultObject->succesful_transactions = $array;
                                        }
                                        $results[$receiver] = $resultObject;
                                    } else {
                                        $resultObject = $results[$receiver];
                                        $resultObject->profit_before_tax += $beforeTax;
                                        $resultObject->profit_after_tax += $beforeTax;
                                        $resultObject->fees += $fee;

                                        if ($receiver != $totalString) {
                                            $resultObject->succesful_transactions[strtotime($date)] = $object;
                                            ksort($resultObject->succesful_transactions);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Separator
    $patreon = get_patreon2_subscriptions();

    if (!empty($patreon)) {
        $receivers = array(
            $totalString,
            "patreon"
        );
        $patreonFee = 0.08;
        $paymentFee = 0.029;
        $feePercentage = $patreonFee + $paymentFee;
        $feeAmount = 0.3;
        $currency = "EUR";

        foreach ($patreon as $patron) {
            $date = $patron->attributes->last_charge_date;

            if ($date !== null) {
                $amount = $patron->attributes->currently_entitled_amount_cents / 100.0;
                $fee = ($amount * $feePercentage) + $feeAmount;
                $beforeTax = $amount - $fee;

                $object = new stdClass();
                $object->user = $patron->attributes->full_name;
                $object->date = $date;
                $object->amount = $beforeTax . " " . $currency;

                foreach ($receivers as $receiver) {
                    if (!array_key_exists($receiver, $results)) {
                        $resultObject = new stdClass();
                        $resultObject->profit_before_tax = $beforeTax;
                        $resultObject->profit_after_tax = $beforeTax;
                        $resultObject->fees = 0.0;
                        $resultObject->tax = 0.0;

                        if ($receiver != $totalString) {
                            $array = array();
                            $array[strtotime($date)] = $object;
                            $resultObject->succesful_transactions = $array;
                        }
                        $results[$receiver] = $resultObject;
                    } else {
                        $resultObject = $results[$receiver];
                        $resultObject->profit_before_tax += $beforeTax;
                        $resultObject->profit_after_tax += $beforeTax;
                        $resultObject->fees += $fee;

                        if ($receiver != $totalString) {
                            $resultObject->succesful_transactions[strtotime($date)] = $object;
                            ksort($resultObject->succesful_transactions);
                        }
                    }
                }
            }
        }
    }
    return $results;
}