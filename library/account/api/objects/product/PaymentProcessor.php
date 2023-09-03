<?php

class PaymentProcessor
{
    private const
        queue_key = array(
        "queue",
        PaymentProcessor::class
    );
    public const
        days_of_processing = "1 day",
        incomplete_account_sources = array(AccountAccounts::POLYMART_URL),
        limit = 1000,
        PAYPAL = AccountAccounts::PAYPAL_EMAIL,
        STRIPE = AccountAccounts::STRIPE_EMAIL,
        ALL_TYPES = array(self::PAYPAL, self::STRIPE);

    public function __construct()
    {
    }

    public function getSource($transaction, $returnIncomplete = false): ?array
    {
        if (isset($transaction->CUSTOM)) {
            $custom = $transaction->CUSTOM;

            if (starts_with($custom, "resource_purchase")) {
                $custom = explode("|", $custom, 5);

                if (sizeof($custom) === 4) {
                    $custom = $custom[1];

                    if (is_numeric($custom)
                        && $custom > 0) {
                        return array(AccountAccounts::SPIGOTMC_URL, $custom, $transaction->EMAIL);
                    }
                }
            } else {
                $custom = base64_decode($custom);

                if ($custom !== false) {
                    $custom = json_decode($custom, true);

                    if (is_object($custom)
                        && isset($custom->user)) {
                        $custom = $custom->user;

                        if (is_numeric($custom)
                            && $custom > 0) {
                            return array(AccountAccounts::POLYMART_URL, $custom, $transaction->EMAIL);
                        }
                    }
                }
            }
        } else if ($returnIncomplete
            && isset($transaction->description)
            && strpos($transaction->description, "Polymart") !== false) {
            $depthKey = get_object_depth_key($transaction, "source.billing_details.email");

            if ($depthKey[0]) {
                return array(AccountAccounts::POLYMART_URL, null, $depthKey[1]);
            }
        }
        return null;
    }

    public function getDetails($transactionID): MethodReply
    {
        $paypal = find_paypal_transactions_by_id($transactionID);

        if (!empty($paypal)) {
            return new MethodReply(
                true,
                AccountAccounts::PAYPAL_EMAIL,
                array_shift($paypal)
            );
        } else {
            $stripe = find_stripe_transactions_by_id($transactionID);

            if (!empty($stripe)) {
                return new MethodReply(
                    true,
                    AccountAccounts::STRIPE_EMAIL,
                    array_shift($stripe)
                );
            }
        }
        return new MethodReply(false);
    }

    public function queue($transactionID): bool
    {
        $cache = get_key_value_pair($this::queue_key);

        if (is_array($cache)) {
            $cache[] = $transactionID;
        } else {
            $cache = array($transactionID);
        }
        return set_key_value_pair($this::queue_key, $cache);
    }

    /**
     * @throws Exception
     */
    public function run(Account $account = null)
    {
        global $refresh_transactions_function;

        try {
            $application = new Application(null);
            $isIndividual = $account !== null;
            $products = $application->getProduct(false);

            if ($products->found()) {
                $products = $products->getResults();
                $productCount = sizeof($products);
                $date = get_current_date();
                $transactionLists = array();
                $failedTransactions = null;

                if ($isIndividual) {
                    foreach ($this::ALL_TYPES as $transactionType) {
                        $transactionLists[$transactionType] = $account->getTransactions()->getSuccessful($transactionType, $productCount);
                    }
                } else {
                    $pastDate = get_past_date($this::days_of_processing);
                    $transactionLists[$this::PAYPAL] = get_all_paypal_transactions($this::limit, $pastDate);
                    $transactionLists[$this::STRIPE] = get_all_stripe_transactions($this::limit, true, $pastDate);
                    $queue = get_key_value_pair($this::queue_key);

                    if (is_array($queue)) {
                        clear_memory($this::queue_key, false, false); // We don't want to share this deletion

                        foreach ($queue as $transactionID) {
                            $transactionDetails = $this->getDetails($transactionID);

                            if ($transactionDetails->isPositiveOutcome()) {
                                $transactionLists[$transactionDetails->getMessage()][$transactionID] = $transactionDetails->getObject();
                            }
                        }
                    }
                }

                foreach ($transactionLists as $transactionType => $transactions) {
                    if (!empty($transactions)) {
                        foreach ($transactions as $transactionID => $transactionDetails) {
                            foreach ($products as $product) {
                                $lookUpID = 0;
                                $skip = true; // Ignore the first iteration
                                $transactionSearch = $product->transaction_search;
                                $transactionSearchEnd = sizeof($transactionSearch) - 1;
                                $email = null;
                                $additionalProducts = null;
                                $duration = null;
                                $credential = false;

                                foreach ($transactionSearch as $transactionSearchRow => $transactionSearchProperties) {
                                    if ($lookUpID !== $transactionSearchProperties->lookup_id) {
                                        if (!$skip) {
                                            $credential = true;
                                            $email = $transactionSearchProperties->email;
                                            $additionalProducts = $transactionSearchProperties->additional_products;
                                            $duration = $transactionSearchProperties->duration;
                                            break;
                                        }
                                        $lookUpID = $transactionSearchProperties->lookup_id;
                                        $skip = false;
                                    }
                                    if ($skip) {
                                        continue;
                                    }
                                    if ($transactionSearchProperties->accepted_account_id != $transactionType) {
                                        $skip = true;
                                        continue;
                                    }
                                    $actualTransactionValue = get_object_depth_key($transactionDetails, $transactionSearchProperties->transaction_key);

                                    if (!$actualTransactionValue[0]) {
                                        $skip = true;
                                        continue;
                                    }
                                    if ($transactionSearchProperties->ignore_case !== null) {
                                        $actualTransactionValue = strtolower($actualTransactionValue[1]);
                                        $expectedTransactionValue = strtolower($transactionSearchProperties->transaction_value);
                                    } else {
                                        $actualTransactionValue = $actualTransactionValue[1];
                                        $expectedTransactionValue = $transactionSearchProperties->transaction_value;
                                    }
                                    switch ($transactionSearchProperties->identification_method) {
                                        case "startsWith":
                                            if (!starts_with($actualTransactionValue, $expectedTransactionValue)) {
                                                $skip = true;
                                            }
                                            break;
                                        case "endsWith":
                                            if (!ends_with($actualTransactionValue, $expectedTransactionValue)) {
                                                $skip = true;
                                            }
                                            break;
                                        case "equals":
                                            if ($actualTransactionValue != $expectedTransactionValue) {
                                                $skip = true;
                                            }
                                            break;
                                        case "contains":
                                            if (!strpos($actualTransactionValue, $expectedTransactionValue) !== false) {
                                                $skip = true;
                                            }
                                            break;
                                        default:
                                            $skip = true;
                                            break;
                                    }

                                    if (!$skip && $transactionSearchRow === $transactionSearchEnd) {
                                        $credential = true;
                                        $email = $transactionSearchProperties->email;
                                        $additionalProducts = $transactionSearchProperties->additional_products;
                                        $duration = $transactionSearchProperties->duration;
                                        break;
                                    }
                                }

                                if ($credential) {
                                    switch ($transactionType) {
                                        case $this::PAYPAL:
                                            $credential = $transactionDetails->EMAIL ?? null;
                                            break;
                                        case $this::STRIPE:
                                            $credential = $transactionDetails->source->billing_details->email ?? null;
                                            break;
                                        default:
                                            $credential = null;
                                            break;
                                    }
                                    if ($credential !== null) {
                                        if ($additionalProducts !== null) {
                                            $explode = explode("|", $additionalProducts);
                                            $additionalProducts = array();

                                            foreach ($explode as $part) {
                                                if (is_numeric($part)) {
                                                    $additionalProducts[$part] = null;
                                                } else {
                                                    $explodeFurther = explode(":", $part, 2);
                                                    $additionalProducts[$explodeFurther[0]] = $explodeFurther[1];
                                                }
                                            }
                                        }
                                        if ($isIndividual) {
                                            if ($failedTransactions === null) {
                                                $failedTransactions = $account->getTransactions()->getFailed(null, $productCount);
                                            }
                                            if (in_array($transactionID, $failedTransactions)) {
                                                $account->getPurchases()->remove($product->id, $transactionID);
                                            } else {
                                                $account->getPurchases()->add(
                                                    $product->id,
                                                    null,
                                                    $transactionID,
                                                    $date,
                                                    $duration,
                                                    $email,
                                                    $additionalProducts,
                                                );
                                            }
                                        } else {
                                            global $alternate_accounts_table;
                                            $account = get_sql_query(
                                                $alternate_accounts_table,
                                                array("account_id"),
                                                array(
                                                    array("accepted_account_id", $transactionType),
                                                    array("deletion_date", null),
                                                    array("credential", $credential)
                                                ),
                                                null,
                                                1
                                            );

                                            if (!empty($account)) {
                                                $account = $application->getAccount($account[0]->account_id);

                                                if (!$account->exists()) {
                                                    $account = $application->getAccount(null, $credential);

                                                    if (!$account->exists()
                                                        || $account->getEmail()->isVerified()) {
                                                        $account = null;
                                                    }
                                                }

                                                if ($account !== null) {
                                                    if ($failedTransactions === null) {
                                                        $furtherPastDate = "180 days";
                                                        $failedTransactions = array_merge(
                                                            get_failed_paypal_transactions(null, $this::limit, $furtherPastDate),
                                                            get_failed_stripe_transactions(null, $this::limit, $furtherPastDate)
                                                        );
                                                    }
                                                    if (in_array($transactionID, $failedTransactions)) {
                                                        $account->getPurchases()->remove($product->id, $transactionID);
                                                    } else {
                                                        $account->getPurchases()->add(
                                                            $product->id,
                                                            null,
                                                            $transactionID,
                                                            $date,
                                                            $duration,
                                                            $email,
                                                            $additionalProducts,
                                                        );
                                                    }
                                                } else {
                                                    $this->sendGeneralPurchaseEmail($credential, $transactionID, $date);
                                                }
                                            } else {
                                                $this->sendGeneralPurchaseEmail($credential, $transactionID, $date);
                                            }
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }

                // Separator

                if (!$isIndividual) {
                    foreach ($products as $product) {
                        if (isset($product->identification[AccountAccounts::BUILTBYBIT_URL])) {
                            $ownerships = get_builtbybit_resource_ownerships(
                                $product->identification[AccountAccounts::BUILTBYBIT_URL],
                            );

                            if (!empty($ownerships)) {
                                global $alternate_accounts_table;

                                foreach ($ownerships as $ownership) {
                                    $query = get_sql_query(
                                        $alternate_accounts_table,
                                        array("account_id"),
                                        array(
                                            array("credential", $ownership->user),
                                            array("accepted_account_id", AccountAccounts::BUILTBYBIT_URL),
                                            array("deletion_date", null),
                                        ),
                                        null,
                                        1
                                    );

                                    if (!empty($query)) {
                                        $account = $application->getAccount($query[0]->account_id);

                                        if ($account->exists()) {
                                            if ($ownership->active) {
                                                $account->getPurchases()->add(
                                                    $product->id,
                                                    null,
                                                    $ownership->transaction_id,
                                                    $ownership->creation_date,
                                                    $ownership->expiration_date,
                                                    "productPurchase",
                                                    true,
                                                );
                                            } else {
                                                $account->getPurchases()->remove($product->id, $ownership->transaction_id);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Separator

                    $all = $application->getWebsiteSession();
                    $all = $all->getAll(array("account_id"), $this::limit);

                    if (!empty($all)) {
                        foreach ($all as $row) {
                            $account = $application->getAccount($row->account_id);

                            if ($account->exists()) {
                                $this->run($account);
                            }
                        }
                    }

                    // Separator
                    end_memory_process($refresh_transactions_function);
                }
            } else if (!$isIndividual) {
                end_memory_process($refresh_transactions_function);
            }
        } catch (Exception $exception) {
            end_memory_process($refresh_transactions_function);
            throw $exception;
        }
    }

    private function sendGeneralPurchaseEmail($email, $transactionID, $date)
    {
        global $unknown_email_processing_table;
        $query = get_sql_query(
            $unknown_email_processing_table,
            array("id"),
            array(
                array("email_address", $email),
                array("transaction_id", $transactionID)
            )
        );

        if (empty($query)
            && sql_insert(
                $unknown_email_processing_table,
                array(
                    "email_address" => $email,
                    "transaction_id" => $transactionID,
                    "creation_date" => $date
                )
            )) {
            send_email_by_plan("account-generalPurchase", $email);
        }
    }
}
