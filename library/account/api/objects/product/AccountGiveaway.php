<?php

class AccountGiveaway
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function hasWon(int|string $productID): bool
    {
        global $giveaway_winners_table;
        set_sql_cache(self::class);
        $query = get_sql_query(
            $giveaway_winners_table,
            array("giveaway_id"),
            array(
                array("account_id", $this->account->getDetail("id"))
            )
        );

        if (!empty($query)) {
            global $product_giveaways_table;

            foreach ($query as $row) {
                set_sql_cache(self::class);

                if (!empty(get_sql_query(
                    $product_giveaways_table,
                    array("id"),
                    array(
                        array("id", $row->giveaway_id),
                        array("product_id", $productID)
                    ),
                    null,
                    1
                ))) {
                    return true;
                }
            }
        }
        return false;
    }

    private function create(int|string|null $productID, int|string $amount, int|string $duration, ?array $exclude = null): bool
    {
        global $product_giveaways_table;
        $array = get_sql_query($product_giveaways_table,
            array("id"),
            array(
                array("application_id", $this->account->getDetail("application_id")),
                array("completion_date", null),
                $productID !== null ? array("product_id", $productID) : ""
            ),
            null,
            1
        ); // get currently running giveaways

        if (empty($array)) { // Create giveaway if no other is currently being run
            if ($productID === null) {
                $nextProductID = null;
                $validProducts = $this->account->getProduct()->find(null, false);

                if ($validProducts->isPositiveOutcome()) {
                    $array = get_sql_query(
                        $product_giveaways_table,
                        array("product_id"),
                        array(
                            array("application_id", $this->account->getDetail("application_id")),
                        ),
                        array(
                            "DESC",
                            "id"
                        ),
                        1
                    ); // get last giveaway, finished or not
                    $validProducts = $validProducts->getObject();
                    $productID = empty($array) ? null : $array[0]->product_id; // get product of last successful giveaway
                    $queueNext = false;

                    // Search for next product to give away
                    foreach ($validProducts as $arrayKey => $product) {
                        if ($product->is_free
                            || $product->giveaway === null
                            || $exclude !== null && in_array($product->id, $exclude)) {
                            unset($validProducts[$arrayKey]);
                        } else {
                            $loopID = $product->id;

                            if ($loopID == $productID) {
                                unset($validProducts[$arrayKey]);
                                $queueNext = true;
                            } else if ($queueNext) {
                                $nextProductID = $loopID; // Take the next product
                                break;
                            }
                        }
                    }

                    // Reset loop if search reached limits
                    if ($nextProductID === null && !empty($validProducts)) {
                        $product = array_shift($validProducts);
                        $nextProductID = $product->id; // Take the first product but not the same
                    }
                }
            } else {
                $nextProductID = $productID;
            }

            if ($nextProductID !== null) {
                // Insert to the database
                global $product_giveaways_table;

                if (sql_insert($product_giveaways_table,
                    array(
                        "application_id" => $this->account->getDetail("application_id"),
                        "product_id" => $nextProductID,
                        "amount" => $amount,
                        "creation_date" => get_current_date(),
                        "expiration_date" => get_future_date($duration)
                    )
                )) {
                    clear_memory(array(self::class), true, 0, function ($value) {
                        return is_array($value);
                    });
                    return true;
                }
            }
        }
        return false;
    }

    public function getCurrent(int|string|null $specifiedProductID,
                               int|string      $amount, int|string $duration,
                               ?array          $exclude = null): MethodReply
    {
        global $product_giveaways_table;
        set_sql_cache(self::class);
        $array = get_sql_query(
            $product_giveaways_table,
            array("id", "product_id", "expiration_date", "amount"),
            array(
                array("application_id", $this->account->getDetail("application_id")),
                array("completion_date", null)
            ),
            null,
            1
        );
        $create = $amount > 0;

        if (!empty($array)) { // Search for existing valid last giveaway
            $object = $array[0];
            $productID = $object->product_id;
            $foundProduct = $this->account->getProduct()->find($productID, false);

            if (!$foundProduct->isPositiveOutcome()) {
                return new MethodReply(false, "Product not found.");
            }
            $foundProduct = $foundProduct->getObject()[0];
            $object->product = $foundProduct;

            if ($create) {
                $date = get_current_date();

                // Separator
                if ($date > $object->expiration_date) {
                    $objectID = $object->id;

                    // Invalidate current giveaway
                    set_sql_query($product_giveaways_table,
                        array(
                            "completion_date" => $date
                        ),
                        array(
                            array("id", $objectID)
                        ),
                        null,
                        1
                    );

                    if ($this->account->getFunctionality()->getResult(AccountFunctionality::RUN_PRODUCT_GIVEAWAY)->isPositiveOutcome()) {
                        // Create new one before searching for winners to avoid concurrency issues
                        if (!$this->create($specifiedProductID, $amount, $duration, $exclude)) {
                            return new MethodReply(false, "Giveaway could not be created. (1)", $object);
                        }
                        global $accounts_table;

                        // Search for available accounts for the giveaway
                        $accounts = get_sql_query(
                            $accounts_table,
                            array("id"),
                            array(
                                array("deletion_date", null),
                                array("application_id", $this->account->getDetail("application_id"))
                            )
                        ); // Search for available accounts
                        $size = sizeof($accounts);

                        if ($size > 0) {
                            $outcome = $this->finalise($objectID, $productID, $foundProduct, $accounts, $size, $object->amount);
                            return new MethodReply(
                                $outcome->isPositiveOutcome(),
                                $outcome->getMessage(),
                                $this->getCurrent(null, 0, $duration)->getObject()
                            );
                        } else {
                            return new MethodReply(
                                false,
                                "Giveaway has no accounts available so it can finish.",
                                $this->getCurrent(null, 0, $duration)->getObject()
                            );
                        }
                    } else {
                        return new MethodReply(false, "Giveaway disabled from finishing.", $object);
                    }
                } else {
                    return new MethodReply(false, "Giveaway not finished yet.", $object);
                }
            } else {
                return new MethodReply(false, "Giveaway found but not selected for recreation.", $object);
            }
        } else if ($create && $this->create($specifiedProductID, $amount, $duration)) {
            // Create new one if non-existent and set create to 'false' to prevent loops
            return $this->getCurrent(null, 0, $duration); // Set amount to 0 to stop creation
        } else {
            return new MethodReply(false, "Giveaway could not be created. (2)");
        }
    }

    public function getLast(int|string|null $productID = null): MethodReply
    {
        if ($this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_PRODUCT_GIVEAWAY)->isPositiveOutcome()) {
            global $product_giveaways_table;
            set_sql_cache(self::class);
            $giveaways = get_sql_query($product_giveaways_table,
                array("id", "product_id"),
                array(
                    array("application_id", $this->account->getDetail("application_id")),
                    array("completion_date", "IS NOT", null),
                    $productID !== null ? array("product_id", $productID) : ""
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($giveaways)) { // Find the last successful giveaway
                $giveaway = $giveaways[0];
                $productWon = $this->account->getProduct()->find($giveaway->product_id, false);

                if (!$productWon->isPositiveOutcome()) {
                    return new MethodReply(false);
                }
                global $giveaway_winners_table;
                set_sql_cache(self::class);
                $winners = get_sql_query(
                    $giveaway_winners_table,
                    array("account_id"),
                    array(
                        array("giveaway_id", $giveaway->id)
                    )
                ); // Find winners of the database

                if (!empty($winners)) {
                    foreach ($winners as $arrayKey => $winner) {
                        $account = $this->account->getNew($winner->account_id);

                        if ($account->exists()
                            && !$account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN)->isPositiveOutcome()
                            && !$account->getFunctionality()->getReceivedAction(AccountFunctionality::RUN_PRODUCT_GIVEAWAY)->isPositiveOutcome()) {
                            $winners[$arrayKey] = $account->getDetail("name");
                        } else {
                            unset($winners[$arrayKey]);
                        }
                    }
                }
                return new MethodReply(
                    true,
                    null,
                    array($winners, $productWon->getObject()[0])
                );
            }
        }
        return new MethodReply(false);
    }

    private function finalise(int|string $objectID,
                              int|string $productID, object $productObject,
                              array      $accountsArray, int|string $accountsAmount, int|string $limit): MethodReply
    {
        $limit = min($limit, $accountsAmount);
        $oneWinner = $limit == 1;

        if ($oneWinner || $limit > 0) {
            global $giveaway_winners_table;
            $winnerCounter = 0;
            $loopCounter = 0;

            while ($loopCounter < $accountsAmount) {
                $loopCounter++;
                $winnerPosition = array_rand($accountsArray);

                if (isset($accountsArray[$winnerPosition])) { // Check if it exists as it may have been removed
                    $account = $this->account->getNew($accountsArray[$winnerPosition]->id); // Get object before unsetting it
                    unset($accountsArray[$winnerPosition]); // Unset object to pick a different winner in the next potential loop

                    // add the product to the winner's account
                    if (!$account->getFunctionality()->getReceivedAction(AccountFunctionality::RUN_PRODUCT_GIVEAWAY)->isPositiveOutcome()
                        && !$account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN)->isPositiveOutcome()
                        && $account->getPurchases()->add($productID)->isPositiveOutcome()) {
                        $ordinalNumber = $oneWinner ? null : add_ordinal_number($winnerCounter);

                        sql_insert(
                            $giveaway_winners_table,
                            array(
                                "giveaway_id" => $objectID,
                                "account_id" => $account->getDetail("id")
                            )
                        ); // store the winner in the database
                        $account->getHistory()->add("won_giveaway", null, $productID);
                        $account->getEmail()->send(
                            "giveawayWinner",
                            array(
                                "position" => ($oneWinner ? "" : $ordinalNumber . " "),
                                "accountName" => $account->getDetail("name"),
                                "productName" => $productObject->name,
                                "grammar" => ($oneWinner ? "the" : "the $ordinalNumber"),
                            )
                        );

                        if ($oneWinner) {
                            return new MethodReply(true, "Giveaway found 1 winner.");
                        } else {
                            $winnerCounter++;

                            if ($winnerCounter == $limit) {
                                return new MethodReply(true, "Giveaway was limited at " . $winnerCounter . " winners.");
                            }
                        }
                    }
                }
            }
            return new MethodReply(true, "Giveaway found " . $winnerCounter . " winners.");
        } else {
            return new MethodReply(false, "Giveaway allowed winner amount is invalid: " . $limit);
        }
    }
}
