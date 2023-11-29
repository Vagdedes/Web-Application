<?php

class PaymentProcessor
{
    private ?int $applicationID;
    private const
        queue_key = array(
        "queue",
        PaymentProcessor::class
    );
    public const
        days_of_processing = "5 days", // This due to the banking system delay of 2-5 business days
        incomplete_account_sources = array(AccountAccounts::POLYMART_URL),
        limit = 1000,
        PAYPAL = AccountAccounts::PAYPAL_EMAIL,
        STRIPE = AccountAccounts::STRIPE_EMAIL,
        ALL_TYPES = array(self::PAYPAL, self::STRIPE);

    public function __construct(?int $applicationID)
    {
        $this->applicationID = $applicationID;
    }

    public function getSource(object $transaction, bool $returnIncomplete = false): ?array
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
            && str_contains($transaction->description, "Polymart")) {
            $depthKey = get_object_depth_key($transaction, "source.billing_details.email");

            if ($depthKey[0]) {
                return array(AccountAccounts::POLYMART_URL, null, $depthKey[1]);
            }
        }
        return null;
    }

    public function getDetails(int|string $transactionID): MethodReply
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

    public function queue(int|string $transactionID): bool
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
    public function run(Account $account = null): void
    {
        global $refresh_transactions_function;

        try {
            $isIndividual = $account !== null;

            if (!$isIndividual) {
                $account = new Account();
            }
            $products = $account->getProduct()->find(null, false);

            if ($products->isPositiveOutcome()) {
                $products = $products->getObject();
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
                        clear_memory($this::queue_key, false, 0, false); // We don't want to share this deletion

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
                                $tier = null;
                                $email = null;
                                $additionalProducts = null;
                                $duration = null;
                                $credential = false;

                                foreach ($transactionSearch as $transactionSearchRow => $transactionSearchProperties) {
                                    if ($lookUpID !== $transactionSearchProperties->lookup_id) {
                                        if (!$skip) {
                                            $credential = true;
                                            $tier = $transactionSearchProperties->tier_id;
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
                                    switch (trim($transactionSearchProperties->identification_method)) {
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
                                            if (!str_contains($actualTransactionValue, $expectedTransactionValue)) {
                                                $skip = true;
                                            }
                                            break;
                                        default:
                                            $skip = true;
                                            break;
                                    }

                                    if (!$skip && $transactionSearchRow === $transactionSearchEnd) {
                                        $credential = true;
                                        $tier = $transactionSearchProperties->tier_id;
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
                                            $additionalProductsArray = explode("|", $additionalProducts);
                                            $additionalProducts = array();

                                            foreach ($additionalProductsArray as $part) {
                                                if (is_numeric($part)) {
                                                    $additionalProducts[$part] = null;
                                                } else {
                                                    $part = explode(":", $part, 2);
                                                    $additionalProducts[$part[0]] = $part[1];
                                                }
                                            }
                                        }
                                        if ($isIndividual) {
                                            if ($failedTransactions === null) {
                                                $failedTransactions = $account->getTransactions()->getFailed(null, $productCount);
                                            }
                                            if (in_array($transactionID, $failedTransactions)) {
                                                $account->getPurchases()->remove($product->id, $tier, $transactionID);
                                            } else {
                                                $account->getPurchases()->add(
                                                    $product->id,
                                                    $tier,
                                                    null,
                                                    $transactionID,
                                                    $date,
                                                    $duration,
                                                    $email,
                                                    $additionalProducts,
                                                );
                                            }
                                        } else {
                                            global $added_accounts_table;
                                            $query = get_sql_query(
                                                $added_accounts_table,
                                                array("account_id"),
                                                array(
                                                    array("accepted_account_id", $transactionType),
                                                    array("deletion_date", null),
                                                    array("credential", $credential)
                                                ),
                                                null,
                                                1
                                            );

                                            if (!empty($query)) {
                                                $account = $account->getNew($query[0]->account_id);

                                                if (!$account->exists()) {
                                                    $account = $account->getNew(null, $credential);

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
                                                        $account->getPurchases()->remove($product->id, $tier, $transactionID);
                                                    } else {
                                                        $account->getPurchases()->add(
                                                            $product->id,
                                                            $tier,
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
                                global $added_accounts_table;

                                foreach ($ownerships as $ownership) {
                                    $query = get_sql_query(
                                        $added_accounts_table,
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
                                        $account = $account->getNew($query[0]->account_id);

                                        if ($account->exists()) {
                                            if ($ownership->active) {
                                                $additionalProducts = array();

                                                foreach ($product->transaction_search as $transactionSearchProperties) {
                                                    if ($transactionSearchProperties->additional_products !== null) {
                                                        foreach (explode("|", $transactionSearchProperties->additional_products) as $part) {
                                                            if (is_numeric($part)) {
                                                                $additionalProducts[$part] = null;
                                                            } else {
                                                                $part = explode(":", $part, 2);
                                                                $additionalProducts[$part[0]] = $part[1];
                                                            }
                                                        }
                                                    }
                                                }
                                                $account->getPurchases()->add(
                                                    $product->id,
                                                    null,
                                                    null,
                                                    $ownership->transaction_id,
                                                    $ownership->creation_date,
                                                    $ownership->expiration_date,
                                                    "productPurchase",
                                                    $additionalProducts,
                                                );
                                            } else {
                                                $account->getPurchases()->remove($product->id, null, $ownership->transaction_id);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Separator

                    $all = $account->getSession();
                    $all = $all->getAlive(array("account_id"), $this::limit);

                    if (!empty($all)) {
                        foreach ($all as $row) {
                            $account = $account->getNew($row->account_id);

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

    private function sendGeneralPurchaseEmail(string $email, int|string $transactionID, string $date): void
    {
        global $unknown_email_processing_table;
        $email = strtolower($email);
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
            $applicationID = $this->applicationID;

            if ($applicationID === null) {
                $applicationID = 0;
            }
            send_email_by_plan($applicationID . "-generalPurchase", $email);
        }
    }
}
