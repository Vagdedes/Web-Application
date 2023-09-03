<?php

class AccountPurchases
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function getCurrent(): array
    {
        global $product_purchases_table;
        $cacheKey = array(self::class, $this->account->getDetail("id"), "current");
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            return $cache;
        }
        $array = array();
        $date = get_current_date();
        $challengeArray = false;
        $applicationID = $this->account->getDetail("application_id");
        $products = new WebsiteProduct($applicationID, false);

        if ($products->found()) {
            foreach ($products->getResults() as $product) {
                if ($product->price === null
                    || $product->required_permission !== null && $this->account->getPermissions()->hasPermission($product->required_permission)) {
                    $object = new stdClass();
                    $object->id = random_number();
                    $object->account_id = $this->account->getDetail("id");
                    $object->product_id = $product->id;
                    $object->exchange_id = null;
                    $object->transaction_id = "patreon";
                    $object->creation_date = $date;
                    $object->expiration_date = null;
                    $object->expiration_notification = null;
                    $object->deletion_date = null;
                    $object->price = $product->price;
                    $object->currency = null;
                    $object->coupon = null;
                    $array[$product->id] = $object;
                    $challengeArray = true;
                }
            }
        }
        $query = get_sql_query(
            $product_purchases_table,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", null),
                array("expiration_notification", null)
            )
        );

        if (!empty($query)) {
            $clearMemory = false;

            foreach ($query as $row) {
                if (!$challengeArray || !array_key_exists($row->product_id, $array)) {
                    if ($row->expiration_date !== null && $row->expiration_date < $date) {
                        $clearMemory = true;
                        $product = new WebsiteProduct($applicationID, true, $row->product_id);

                        if ($product->found()) {
                            $this->account->getEmail()->send("productExpiration",
                                array(
                                    "productName" => $product->getFirstResult()->name,
                                )
                            );
                        }
                    } else {
                        $array[$row->product_id] = $row;
                    }
                }
            }

            if ($clearMemory) {
                clear_memory(array(self::class, RegisteredBuyers::class), true);
            } else {
                global $sql_max_cache_time;
                set_key_value_pair($cacheKey, $array, $sql_max_cache_time);
            }
        } else {
            global $sql_max_cache_time;
            set_key_value_pair($cacheKey, $array, $sql_max_cache_time);
        }
        return $array;
    }

    public function getExpired(): array
    {
        global $product_purchases_table;
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            $product_purchases_table,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", "IS NOT", null),
                array("expiration_date", "IS NOT", null),
                array("expiration_date", "<", get_current_date()),
            ),
        );

        if (!empty($query)) {
            $clearMemory = false;
            $applicationID = $this->account->getDetail("application_id");

            foreach ($query as $key => $row) {
                if ($row->expiration_notification === null) {
                    $row->expiration_notification = 1;
                    $query[$key] = $row;
                    $clearMemory = true;
                    $product = new WebsiteProduct($applicationID, true, $row->product_id);

                    if ($product->found()) {
                        $this->account->getEmail()->send("productExpiration",
                            array(
                                "productName" => $product->getFirstResult()->name,
                            )
                        );
                    }
                }
            }

            if ($clearMemory) {
                clear_memory(array(self::class, RegisteredBuyers::class), true);
            }
        }
        return $query;
    }

    public function getDeleted(): array
    {
        global $product_purchases_table;
        set_sql_cache(null, self::class);
        return get_sql_query(
            $product_purchases_table,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", null)
            ),
        );
    }

    public function owns($productID): MethodReply
    {
        $array = $this->getCurrent();

        if (!empty($array)) {
            foreach ($array as $row) {
                if ($row->product_id == $productID) {
                    return new MethodReply(true, null, $row);
                }
            }
        }
        return new MethodReply(false);
    }

    public function owned($productID): MethodReply
    {
        $array = $this->getExpired();

        if (!empty($array)) {
            foreach ($array as $row) {
                if ($row->product_id == $productID) {
                    return new MethodReply(true, null, $row);
                }
            }
        }
        return new MethodReply(false);
    }

    public function add($productID, $coupon = null,
                        $transactionID = null,
                        $creationDate = null, $duration = null,
                        $sendEmail = null,
                        $additionalProducts = null): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::BUY_PRODUCT,
            $this->account
        );
        $functionality = $functionality->getResult();

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        $product = new WebsiteProduct($this->account->getDetail("application_id"), true, $productID);

        if (!$product->found()) {
            return new MethodReply(false, "This product does not exist.");
        }
        $product = $product->getFirstResult();
        $purchase = $this->owns($productID);

        if ($purchase->isPositiveOutcome()) {
            return new MethodReply(false, "This product is already owned.");
        }
        global $product_purchases_table;

        if (!empty(get_sql_query(
            $product_purchases_table,
            array("id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("product_id", $productID),
                array("transaction_id", $transactionID)
            ),
            null,
            1
        ))) {
            return new MethodReply(false, "This transaction has already been processed for this product.");
        }
        $hasCoupon = $coupon !== null;
        $price = $product->price;

        if ($hasCoupon) {
            $functionality = new WebsiteFunctionality(
                $this->account->getDetail("application_id"),
                WebsiteFunctionality::USE_COUPON,
                $this->account
            );
            $functionality = $functionality->getResult();

            if (!$functionality->isPositiveOutcome()) {
                return new MethodReply(false, $functionality->getMessage());
            }
            $object = new ProductCoupon(
                $coupon,
                $this->account->getDetail("id"),
                $productID
            );

            if (!$object->canUse()) {
                return new MethodReply(false, "This coupon is invalid, overused or has expired.");
            }

            if ($price !== null) {
                $price = $price * $object->getDecimalMultiplier();
            }
        }
        if ($creationDate === null) {
            $creationDate = get_current_date();
        }
        if ($duration !== null && !is_date($duration)) {
            $duration = get_future_date($duration);
        }
        if (!sql_insert(
            $product_purchases_table,
            array(
                "account_id" => $this->account->getDetail("id"),
                "product_id" => $productID,
                "transaction_id" => $transactionID,
                "creation_date" => $creationDate,
                "expiration_date" => $duration,
                "price" => $price,
                "currency" => $product->currency,
                "coupon" => $coupon
            )
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        clear_memory(array(self::class, RegisteredBuyers::class), true);

        if (!$this->account->getHistory()->add("buy_product", null, $productID)) {
            return new MethodReply(false, "Failed to update user history (1).");
        }
        if ($hasCoupon) {
            if (!$this->account->getHistory()->add("use_coupon", null, $coupon)) {
                return new MethodReply(false, "Failed to update user history (2).");
            }
            clear_memory(array(ProductCoupon::class), true);
        }
        if ($sendEmail !== null) {
            $details = array(
                "productID" => $productID,
                "productName" => $product->name,
                "transactionID" => $transactionID,
                "creationDate" => $product->name,
                "additionalProducts" => $additionalProducts,
            );

            if ($hasCoupon) {
                $details["coupon"] = $coupon;
            }
            if ($duration !== null) {
                $details["expirationDate"] = $duration;
            }
            $this->account->getEmail()->send($sendEmail, $details);
        }

        if ($additionalProducts !== null) {
            foreach ($additionalProducts as $additionalProduct => $additionalProductDuration) {
                $this->add(
                    $additionalProduct,
                    null,
                    $transactionID,
                    $creationDate,
                    $additionalProductDuration === null ? $duration : $additionalProductDuration,
                    $sendEmail
                );
            }
        }
        return new MethodReply(true, "Successfully made new purchase.");
    }

    public function remove($productID, $transactionID = null): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::REMOVE_PRODUCT,
            $this->account
        );
        $functionality = $functionality->getResult();

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        $purchase = $this->owns($productID);

        if (!$purchase->isPositiveOutcome()) {
            return new MethodReply(false, "Cannot remove purchase that is not owned.");
        }
        global $product_purchases_table;

        if ($transactionID !== null
            && $purchase->getObject()->transaction_id != $transactionID) {
            return new MethodReply(false, "Purchase found but transaction-id is not matched.");
        }
        if (!set_sql_query(
            $product_purchases_table,
            array("deletion_date" => get_current_date()),
            array(
                array("id", $purchase->getObject()->id)
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }
        clear_memory(array(self::class, RegisteredBuyers::class), true);

        if (!$this->account->getHistory()->add("remove_product", null, $productID)) {
            return new MethodReply(false, "Failed to update user history (1).");
        }
        return new MethodReply(true, "Successfully removed purchase.");
    }

    public function exchange($productID, $newProductID, $sendEmail = true): MethodReply
    {
        $functionality = new WebsiteFunctionality(
            $this->account->getDetail("application_id"),
            WebsiteFunctionality::EXCHANGE_PRODUCT,
            $this->account
        );
        $functionality = $functionality->getResult();

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if ($productID === $newProductID) {
            return new MethodReply(false, "Cannot exchange purchase with the same product.");
        }
        $applicationID = $this->account->getDetail("application_id");
        $currentProduct = new WebsiteProduct($applicationID, true, $productID);

        if (!$currentProduct->found()) {
            return new MethodReply(false, "Failed to find current product.");
        }
        $newProduct = new WebsiteProduct($applicationID, true, $newProductID);

        if (!$newProduct->found()) {
            return new MethodReply(false, "Failed to find new product.");
        }
        $purchase = $this->owns($productID);

        if (!$purchase->isPositiveOutcome()) {
            return new MethodReply(false, "Cannot exchange purchase that's not owned.");
        }
        $purchase = $this->owns($newProductID);

        if ($purchase->isPositiveOutcome()) {
            return new MethodReply(false, "Cannot exchange purchase that's already owned.");
        }
        global $product_purchases_table;
        $date = get_current_date();
        $purchase = $purchase->getObject();
        $purchaseID = $purchase->id;
        unset($purchase->id);
        $purchase->creation_date = $date;
        $purchase->product_id = $newProductID;

        if (!sql_insert(
            $product_purchases_table,
            json_decode(json_encode($purchase), true)
        )) {
            return new MethodReply(false, "Failed to interact with the database (1).");
        }
        $query = get_sql_query(
            $product_purchases_table,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("product_id", $newProductID),
                array("creation_date", $date)
            ),
            null,
            1
        );

        if (empty($query)) {
            return new MethodReply(false, "Failed to interact with the database (2).");
        }
        if (!set_sql_query(
            $product_purchases_table,
            array(
                "exchange_id" => $query[0]->id,
                "deletion_date" => $date
            ),
            array(
                array("id", $purchaseID)
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database (3).");
        }
        clear_memory(array(self::class, RegisteredBuyers::class), true);

        if (!$this->account->getHistory()->add("exchange_product", $productID, $newProductID)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($sendEmail) {
            $this->account->getEmail()->send("productExchange",
                array(
                    "currentProductName" => $currentProduct->getFirstResult()->name,
                    "newProductName" => $newProduct->getFirstResult()->name
                )
            );
        }
        return new MethodReply(true, "Successfully exchanged purchase with a different product.");
    }
}
