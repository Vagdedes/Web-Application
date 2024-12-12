<?php

class AccountPurchases
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getCurrent(bool $databaseOnly = false): array
    {
        if (!$this->account->exists()) {
            return array();
        }
        $array = array();
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::PRODUCT_PURCHASES_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", null),
                array("expiration_notification", null)
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                if ($row->expiration_date !== null && $row->expiration_date < $date) {
                    $product = $this->account->getProduct()->find($row->product_id);

                    if ($product->isPositiveOutcome()) {
                        $this->account->getEmail()->send("productExpiration",
                            array(
                                "productName" => $product->getObject()[0]->name,
                            )
                        );
                    }
                } else {
                    $array[$row->product_id] = $row;

                    if ($row->tier_id !== null) {
                        $product = $this->account->getProduct()->find($row->product_id);

                        if ($product->isPositiveOutcome()) {
                            $tier = $product->getObject()[0]->tiers->all[$row->tier_id] ?? null;

                            if ($tier !== null && $tier->give_permission !== null) {
                                $this->account->getPermissions()->addSystemPermission($tier->give_permission);
                            }
                        }
                    }
                }
            }
        }

        if (!$databaseOnly) {
            $products = $this->account->getProduct()->find(null, false);

            if ($products->isPositiveOutcome()) {
                foreach ($products->getObject() as $product) {
                    if (!array_key_exists($product->id, $array)) {
                        $tierObject = null;

                        if (!$product->is_free) {
                            foreach ($product->tiers->paid as $tier) {
                                if (!empty($tier->required_products)
                                    || !empty($tier->required_permission)
                                    || !empty($tier->required_patreon_tiers)) {
                                    if (empty($tier->required_products)) {
                                        $passed = true;
                                    } else {
                                        $passed = false;

                                        foreach ($tier->required_products as $requiredProduct) {
                                            if (array_key_exists($requiredProduct, $array)) {
                                                $passed = true;
                                                break;
                                            }
                                        }
                                    }

                                    if ($passed
                                        && (empty($tier->required_permission)
                                            || $this->account->getPermissions()->hasPermission($tier->required_permission))
                                        && (empty($tier->required_patreon_tiers)
                                            || $this->account->getPatreon()->retrieve(
                                                $tier->required_patreon_tiers,
                                                $tier->required_patreon_cents
                                            )->isPositiveOutcome())) {
                                        $tierObject = $tier;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($tierObject !== null) {
                            if ($tierObject->give_permission !== null) {
                                $this->account->getPermissions()->addSystemPermission($tierObject->give_permission);
                            }
                            $object = new stdClass();
                            $object->id = random_number();
                            $object->account_id = $this->account->getDetail("id");
                            $object->product_id = $product->id;
                            $object->tier_id = $tierObject?->id;
                            $object->exchange_id = null;
                            $object->transaction_id = null;
                            $object->creation_date = $date;
                            $object->expiration_date = null;
                            $object->expiration_notification = null;
                            $object->deletion_date = null;
                            $object->coupon = null;
                            $array[$product->id] = $object;
                        }
                    }
                }
            }
        }
        return $array;
    }

    public function getExpired(): array
    {
        if (!$this->account->exists()) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::PRODUCT_PURCHASES_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", "IS NOT", null),
                array("expiration_date", "IS NOT", null),
                array("expiration_date", "<", get_current_date()),
            ),
        );

        if (!empty($query)) {
            foreach ($query as $key => $row) {
                if ($row->expiration_notification === null) {
                    $row->expiration_notification = 1;
                    $query[$key] = $row;
                    $product = $this->account->getProduct()->find($row->product_id);

                    if ($product->isPositiveOutcome()) {
                        $this->account->getEmail()->send("productExpiration",
                            array(
                                "productName" => $product->getObject()[0]->name,
                            )
                        );
                    }
                }
            }
        }
        return $query;
    }

    public function getDeleted(): array
    {
        if (!$this->account->exists()) {
            return array();
        }
        return get_sql_query(
            AccountVariables::PRODUCT_PURCHASES_TABLE,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", null)
            ),
        );
    }

    public function owns(int|string $productID, int|string|null $tierID = null,
                         bool       $databaseOnly = false): MethodReply
    {
        $array = $this->getCurrent($databaseOnly);

        if (!empty($array)) {
            $hasTier = $tierID !== null;

            foreach ($array as $row) {
                if ($row->product_id == $productID
                    && (!$hasTier || $row->tier_id == $tierID)) {
                    return new MethodReply(true, null, $row);
                }
            }
        }
        return new MethodReply(false);
    }

    public function owned(int|string $productID, int|string|null $tierID = null): MethodReply
    {
        $array = $this->getExpired();

        if (!empty($array)) {
            $hasTier = $tierID !== null;

            foreach ($array as $row) {
                if ($row->product_id == $productID
                    && (!$hasTier || $row->tier_id == $tierID)) {
                    return new MethodReply(true, null, $row);
                }
            }
        }
        return new MethodReply(false);
    }

    public function ownsMultiple(array $products, bool $databaseOnly = false): MethodReply
    {
        $array = $this->getCurrent($databaseOnly);

        if (!empty($array)) {
            foreach ($products as $productID => $tierID) {
                $hasTier = $tierID !== null;

                foreach ($array as $row) {
                    if ($row->product_id == $productID
                        && (!$hasTier || $row->tier_id == $tierID)) {
                        return new MethodReply(true);
                    }
                }
            }
        }
        return new MethodReply(false);
    }

    public function add(int|string            $productID, int|string|null $tierID = null,
                        int|string|float|null $coupon = null,
                        int|string|null       $transactionID = null,
                        string                $creationDate = null, int|string|null $duration = null,
                        string|null           $sendEmail = null,
                        ?array                $additionalProducts = null): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::BUY_PRODUCT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $product = $this->account->getProduct()->find($productID, false);

        if (!$product->isPositiveOutcome()) {
            return new MethodReply(false, $product->getMessage());
        }
        $product = $product->getObject()[0];

        if ($product->is_free) {
            return new MethodReply(false, "This product is free and cannot be purchased.");
        }
        $price = null;
        $currency = null;

        if ($tierID === null) {
            $purchase = $this->owns($productID, null, true);

            if ($purchase->isPositiveOutcome()) {
                return new MethodReply(false, "This product's tier is already owned (1).");
            }
        } else {
            foreach ($product->tiers->all as $tier) {
                if ($tier->id == $tierID) {
                    $price = $tier->price;
                    $currency = $tier->currency;
                    break;
                }
            }
            if ($price === null) {
                return new MethodReply(false, "This product does not have a price (2).");
            }
            if ($currency === null) {
                return new MethodReply(false, "This product does not have a currency (2).");
            }
            $purchase = $this->owns($productID, $tierID, true);

            if ($purchase->isPositiveOutcome()) {
                return new MethodReply(false, "This product's tier is already owned (2).");
            }
        }
        if ($transactionID !== null
            && !empty(get_sql_query(
                AccountVariables::PRODUCT_PURCHASES_TABLE,
                array("id"),
                array(
                    array("account_id", $this->account->getDetail("id")),
                    array("product_id", $productID),
                    array("transaction_id", $transactionID)
                ),
                null,
                1
            ))) {
            if (!empty($additionalProducts)) {
                foreach ($additionalProducts as $additionalProduct => $additionalProductDuration) {
                    $this->add(
                        $additionalProduct,
                        null,
                        null,
                        $transactionID,
                        $creationDate,
                        $additionalProductDuration === null ? $duration : $additionalProductDuration,
                        $sendEmail,
                    );
                }
            }
            return new MethodReply(false, "This transaction has already been processed for this product.");
        }
        $hasCoupon = $coupon !== null;

        if ($hasCoupon) {
            $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::USE_COUPON);

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
            AccountVariables::PRODUCT_PURCHASES_TABLE,
            array(
                "account_id" => $this->account->getDetail("id"),
                "product_id" => $productID,
                "tier_id" => $tierID,
                "transaction_id" => $transactionID,
                "creation_date" => $creationDate,
                "expiration_date" => $duration,
                "price" => $price,
                "currency" => $currency,
                "coupon" => $coupon
            )
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }

        if (!$this->account->getHistory()->add("buy_product", null, $productID)) {
            return new MethodReply(false, "Failed to update user history (1).");
        }
        if ($hasCoupon) {
            if (!$this->account->getHistory()->add("use_coupon", null, $coupon)) {
                return new MethodReply(false, "Failed to update user history (2).");
            }
        }
        if ($sendEmail !== null) {
            $details = array(
                "productID" => $productID,
                "productName" => strip_tags($product->name),
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

        if (!empty($additionalProducts)) {
            foreach ($additionalProducts as $additionalProduct => $additionalProductDuration) {
                $this->add(
                    $additionalProduct,
                    null,
                    null,
                    $transactionID,
                    $creationDate,
                    $additionalProductDuration === null ? $duration : $additionalProductDuration,
                    $sendEmail,
                );
            }
        }
        return new MethodReply(true, "Successfully made new purchase.");
    }

    public function remove(int|string      $productID, int|string|null $tierID = null,
                           int|string|null $transactionID = null): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::REMOVE_PRODUCT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        $purchase = $this->owns($productID, $tierID, true);

        if (!$purchase->isPositiveOutcome()) {
            return new MethodReply(false, "Cannot remove purchase that is not owned.");
        }
        if ($transactionID !== null
            && $purchase->getObject()->transaction_id != $transactionID) {
            return new MethodReply(false, "Purchase found but transaction-id is not matched.");
        }
        if (!set_sql_query(
            AccountVariables::PRODUCT_PURCHASES_TABLE,
            array("deletion_date" => get_current_date()),
            array(
                array("id", $purchase->getObject()->id)
            ),
            null,
            1
        )) {
            return new MethodReply(false, "Failed to interact with the database.");
        }

        if (!$this->account->getHistory()->add("remove_product", null, $productID)) {
            return new MethodReply(false, "Failed to update user history (1).");
        }
        return new MethodReply(true, "Successfully removed purchase.");
    }

    public function exchange(int|string $productID, int|string|null $tierID,
                             int|string $newProductID, int|string|null $newTierID,
                             bool       $sendEmail = true): MethodReply
    {
        $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::EXCHANGE_PRODUCT);

        if (!$functionality->isPositiveOutcome()) {
            return new MethodReply(false, $functionality->getMessage());
        }
        if (!$this->account->exists()) {
            return new MethodReply(false, "No account found.");
        }
        if ($productID === $newProductID) {
            return new MethodReply(false, "Cannot exchange purchase with the same product.");
        }
        $currentProduct = $this->account->getProduct()->find($productID, false);

        if (!$currentProduct->isPositiveOutcome()) {
            return new MethodReply(false, $currentProduct->getMessage());
        }
        $newProduct = $this->account->getProduct()->find($newProductID, false);

        if (!$newProduct->isPositiveOutcome()) {
            return new MethodReply(false, $newProduct->getMessage());
        }
        $purchase = $this->owns($productID, $tierID, true);

        if (!$purchase->isPositiveOutcome()) {
            return new MethodReply(false, "Cannot exchange purchase that's not owned.");
        }
        $purchase = $this->owns($newProductID, $newTierID, true);

        if ($purchase->isPositiveOutcome()) {
            return new MethodReply(false, "Cannot exchange purchase that's already owned.");
        }
        $date = get_current_date();
        $purchase = $purchase->getObject();
        $purchaseID = $purchase->id;
        unset($purchase->id);
        $purchase->tier_id = $newTierID;
        $purchase->creation_date = $date;
        $purchase->product_id = $newProductID;

        if (!sql_insert(
            AccountVariables::PRODUCT_PURCHASES_TABLE,
            json_decode(json_encode($purchase), true) // Convert object to array
        )) {
            return new MethodReply(false, "Failed to interact with the database (1).");
        }
        $query = get_sql_query(
            AccountVariables::PRODUCT_PURCHASES_TABLE,
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
            AccountVariables::PRODUCT_PURCHASES_TABLE,
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

        if (!$this->account->getHistory()->add("exchange_product", $productID, $newProductID)) {
            return new MethodReply(false, "Failed to update user history.");
        }
        if ($sendEmail) {
            $this->account->getEmail()->send("productExchange",
                array(
                    "currentProductName" => strip_tags($currentProduct->getObject()[0]->name),
                    "newProductName" => strip_tags($newProduct->getObject()[0]->name)
                )
            );
        }
        return new MethodReply(true, "Successfully exchanged purchase with a different product.");
    }

}
