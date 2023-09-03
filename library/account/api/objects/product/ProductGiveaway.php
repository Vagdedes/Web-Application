<?php

class ProductGiveaway
{
    private ?int $applicationID;

    public function __construct($applicationID)
    {
        $this->applicationID = $applicationID;
    }

    public function hasWon(Account $account, $productID): bool
    {
        global $giveaway_winners_table;
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            $giveaway_winners_table,
            array("giveaway_id"),
            array(
                array("account_id", $account->getDetail("id"))
            )
        );

        if (!empty($query)) {
            global $product_giveaways_table;

            foreach ($query as $row) {
                set_sql_cache(null, self::class);

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

    private function create($amount, $duration): bool
    {
        global $product_giveaways_table;
        $array = get_sql_query($product_giveaways_table,
            array("id"),
            array(
                array("completion_date", null)
            ),
            null,
            1
        ); // get currently running giveaways

        if (empty($array)) { // Create giveaway if no other is currently being run
            $array = get_sql_query($product_giveaways_table,
                array("product_id"),
                array(
                    array("completion_date", "IS NOT", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            ); // get last successful giveaway

            if (!empty($array)) {
                $nextProductID = null;
                $validProducts = new WebsiteProduct($this->applicationID, false);
                $validProducts = $validProducts->getResults();

                if (!empty($validProducts)) {
                    $productID = $array[0]->product_id; // get product of last successful giveaway
                    $queueNext = false;

                    // Search for next product to give away
                    foreach ($validProducts as $arrayKey => $product) {
                        if ($product->price !== null
                            && $product->show_in_list !== null
                            && !empty($product->downloads)) {
                            $loopID = $product->id;

                            if ($loopID == $productID) {
                                $queueNext = true;
                            } else if ($queueNext) {
                                $nextProductID = $loopID;
                                break;
                            }
                        } else {
                            unset($validProducts[$arrayKey]);
                        }
                    }

                    // Reset loop if search reached limits
                    if ($nextProductID == null) {
                        foreach ($validProducts as $product) {
                            $loopID = $product->id;

                            if ($loopID != $productID) {
                                $nextProductID = $loopID;
                                break;
                            }
                        }
                    }

                    if ($nextProductID !== null) {
                        // Insert to the database
                        global $product_giveaways_table;

                        if (sql_insert($product_giveaways_table,
                            array(
                                "product_id" => $nextProductID,
                                "amount" => $amount,
                                "creation_date" => get_current_date(),
                                "expiration_date" => get_future_date($duration)
                            )
                        )) {
                            clear_memory(array(self::class), true);
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function getCurrent($create = false, $amount = 1, $duration = "14 days"): MethodReply
    {
        global $product_giveaways_table;
        set_sql_cache(null, self::class);
        $array = get_sql_query(
            $product_giveaways_table,
            array("id", "product_id", "expiration_date", "amount"),
            array(
                array("completion_date", null)
            ),
            null,
            1
        );

        if (!empty($array)) { // Search for existing valid last giveaway
            $object = $array[0];
            $productID = $object->product_id;
            $foundProduct = new WebsiteProduct($this->applicationID, false, $productID);

            if (!$foundProduct->hasResults()) {
                return new MethodReply(false);
            }
            $foundProduct = $foundProduct->getFirstResult();
            $object->product = $foundProduct;

            if ($create) {
                $date = get_current_date();

                // Separator
                if ($date > $object->expiration_date) {
                    $functionality = new WebsiteFunctionality($this->applicationID, WebsiteFunctionality::RUN_PRODUCT_GIVEAWAY);

                    if ($functionality->getResult()->isPositiveOutcome()) {
                        global $accounts_table;
                        $objectID = $object->id;

                        // Invalidate current giveaway and create new one before searching for winners to avoid concurrency issues
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
                        $this->create($amount, $duration);

                        // Search for available accounts for the giveaway
                        $accounts = get_sql_query(
                            $accounts_table,
                            array("id"),
                            array(
                                array("deletion_date", null),
                                array("application_id", $this->applicationID)
                            )
                        ); // Search for available accounts
                        $size = sizeof($accounts);

                        if ($size > 0) {
                            $this->finalise($objectID, $productID, $foundProduct, $accounts, $size, $object->amount);
                        }
                    }
                }
            }
            return new MethodReply(true, null, $object);
        } else if ($create && $this->create($amount, $duration)) {
            // Create new one if non-existent
            return $this->getCurrent();
        } else {
            return new MethodReply(false);
        }
    }

    public function getLast(): MethodReply
    {
        $functionality = new WebsiteFunctionality($this->applicationID, WebsiteFunctionality::VIEW_PRODUCT_GIVEAWAY);

        if ($functionality->getResult()->isPositiveOutcome()) {
            global $product_giveaways_table;
            set_sql_cache(null, self::class);
            $giveaways = get_sql_query($product_giveaways_table,
                array("id", "product_id"),
                array(
                    array("completion_date", "IS NOT", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($giveaways)) { // Find the last successful giveaway
                $giveaway = $giveaways[0];
                $productWon = new WebsiteProduct($this->applicationID, false, $giveaway->product_id);

                if (!$productWon->hasResults()) {
                    return new MethodReply(false);
                }
                global $giveaway_winners_table;
                set_sql_cache(null, self::class);
                $winners = get_sql_query(
                    $giveaway_winners_table,
                    array("account_id"),
                    array(
                        array("giveaway_id", $giveaway->id)
                    )
                ); // Find winners of the database

                if (!empty($winners)) {
                    foreach ($winners as $arrayKey => $winner) {
                        $account = new Account($this->applicationID, $winner->account_id);

                        if ($account->exists()
                            && !$account->getModerations()->getReceivedAction(WebsiteModeration::ACCOUNT_BAN)->isPositiveOutcome()
                            && !$account->getModerations()->getBlockedFunctionality(WebsiteFunctionality::RUN_PRODUCT_GIVEAWAY)->isPositiveOutcome()) {
                            $winners[$arrayKey] = $account->getDetail("name");
                        } else {
                            unset($winners[$arrayKey]);
                        }
                    }
                }
                return new MethodReply(
                    true,
                    null,
                    array($winners, $productWon->getFirstResult())
                );
            }
        }
        return new MethodReply(false);
    }

    private function finalise($objectID, $productID, $productObject,
                              $accountsArray, $accountsAmount, $limit): bool
    {
        $limit = min($limit, $accountsAmount);
        $oneWinner = $limit == 1;

        if ($oneWinner || $limit > 0) {
            global $giveaway_winners_table;
            $winnerCounter = 0;
            $loopCounter = 0;

            while ($loopCounter < $accountsAmount) {
                $loopCounter++;
                $winnerPosition = rand(0, $accountsAmount - 1);

                if (isset($accountsArray[$winnerPosition])) { // Check if it exists as it may have been removed
                    $account = new Account($this->applicationID, $accountsArray[$winnerPosition]->id, null, null, false); // Get object before unsetting it
                    unset($accountsArray[$winnerPosition]); // Unset object to pick a different winner in the next potential loop

                    // add the product to the winner's account
                    if (!$account->getModerations()->getBlockedFunctionality(WebsiteFunctionality::RUN_PRODUCT_GIVEAWAY)->isPositiveOutcome()
                        && !$account->getModerations()->getReceivedAction(WebsiteModeration::ACCOUNT_BAN)->isPositiveOutcome()
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
                            $winnerCounter = 1;
                            break;
                        } else {
                            $winnerCounter++;

                            if ($winnerCounter == $limit) {
                                break;
                            }
                        }
                    }
                }
            }
            return $winnerCounter > 0;
        }
        return false;
    }
}
