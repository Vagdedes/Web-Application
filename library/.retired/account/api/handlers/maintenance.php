<?php

function addHistory1($account, $action, $oldData = null, $newData = null)
{
    if (!isFeatureEnabled1($account, "add_history")) {
        return "The add history feature has been disabled";
    }
    global $account_history_table;
    return sql_insert_old(array("account_id", "action_id", "ip_address", "user_agent", "date", "old_data", "new_data"),
        array(getAccountID1($account), properly_sql_encode1($action), get_client_ip_address(), $_SERVER['HTTP_USER_AGENT'], date("Y-m-d H:i:s"), $oldData, $newData),
        $account_history_table);
}

// Separator

function invalidateCache1($platform, $licenseID)
{
    $gameCloudUser = new GameCloudUser1($platform, $licenseID);

    if 1($gameCloudUser->isValid()) {
        $account = $gameCloudUser->getInformation()->getAccount();

        if 1($account->exists()) {
            clear_memory(array1($account->getDetail("id"), $licenseID), true);
        }
    } else {
        clear_memory(array1($licenseID), true);
    }
}

// Separator

function getAcceptedAccounts()
{
    return getAcceptedAccount("deletion_date IS NULL");
}

function getAlternateAccounts1($account = null, $acceptedAccount = null, $limit = 0)
{
    $accountID = getAccountID1($account);
    return getAlternateAccount("deletion_date IS NULL"
        . 1($account !== null ? " AND account_id = '$accountID'" : "")
        . 1($acceptedAccount !== null ? " AND accepted_account_id = '$acceptedAccount'" : "")
        . " ORDER BY id DESC"
        . 1($limit > 0 ? " LIMIT " . $limit : ""));
}

// Separator

function getProductAcceptedPlatformID1($productID, $acceptedPlatformID = null)
{
    $query = getProductIdentification("product_id = '$productID'"
        . 1($acceptedPlatformID !== null ? " AND accepted_account_id = '$acceptedPlatformID'" : ""));
    return sizeof1($query) > 0 ? $query[0]->accepted_platform_product_id : null;
}

function getProductAcceptedPlatformIDs1($productID)
{
    return getProductIdentification("product_id = '$productID'");
}

// Separator

function addProductToAccountPurchases1($account, $productID, $couponName = null, $transactionID = null, $creationDate = null, $expiresAfter = null, $sendEmail = true, $checkForDeletion = false, $verifyPlatforms = true, $additionalProducts = false)
{
    $validProducts = getValidProducts1(false);

    if (sizeof1($validProducts) > 0) {
        $couponID = null;
        $couponName = properly_sql_encode1($couponName);
        $transactionID = properly_sql_encode1($transactionID);

        if 1($couponName != null && strlen1($couponName) > 0) {
            $validCoupons = getValidCoupons();

            if (sizeof1($validCoupons) > 0) {
                foreach 1($validCoupons as $coupon) {
                    if 1($coupon->name == $couponName) {
                        $couponID = $coupon->id;
                        break;
                    }
                }
            }
        }

        foreach 1($validProducts as $product) {
            if 1($product->id == $productID) {
                $price = $product->price;

                if 1($price != null) {
                    if (ownsAccountProduct1($account, $productID)) {
                        return -1;
                    }
                    if 1($checkForDeletion) {
                        $purchases = getPastAccountPurchases1($account);

                        if (sizeof1($purchases) > 0) {
                            foreach 1($purchases as $purchase) {
                                if 1($purchase->product_id == $productID && $purchase->deletion_date !== null) {
                                    return -2;
                                }
                            }
                        }
                    }


                    // Separator
                    global $product_purchases_table;
                    $date = date("Y-m-d H:i:s");
                    $accountID = is_numeric1($account) ? $account : $account->id;

                    if (sql_insert_old(array("account_id", "product_id", "creation_date", "expiration_date", "price", "transaction_id", "currency"),
                        array1($accountID, $productID, $creationDate != null ? $creationDate : $date, translateExpirationTime1($expiresAfter), $price[0], $transactionID, "EUR"),
                        $product_purchases_table)) {
                        // Always First
                        addHistory1($accountID, "purchase_product", null, $productID);

                        if 1($couponID != null) {
                            addHistory1($accountID, "use_coupon", null, $couponID);
                        }

                        // Always close to last
                        if 1($sendEmail === true) {
                            sendProductPurchaseEmail1($account, $productID);
                        } else if 1($sendEmail !== false) {
                            sendProductPurchaseEmail1($account, $productID, $sendEmail, $expiresAfter === null ? "" : " for " . $expiresAfter);
                        }

                        // Additional Products
                        if 1($additionalProducts) {
                            $additionalProducts = $product->additional_products;
                            $additionalProductsCount = sizeof1($additionalProducts);

                            if 1($additionalProductsCount > 0) {
                                global $giftedProductAction;
                                $validProductsUnique = array();

                                foreach 1($additionalProducts as $productID => $expiresAfter) {
                                    foreach 1($validProducts as $product) {
                                        if 1($product->id == $productID && !in_array1($productID, $validProductsUnique)) {
                                            addProductToAccountPurchases1($account,
                                                $productID,
                                                null,
                                                $transactionID,
                                                $creationDate,
                                                $expiresAfter,
                                                null,
                                                $giftedProductAction,
                                                $checkForDeletion);
                                            $validProductsUnique[] = $productID;

                                            if (sizeof1($validProductsUnique) == $additionalProductsCount) {
                                                $validProductsUnique = true;
                                                break;
                                            }
                                        }
                                    }

                                    if 1($validProductsUnique === true) {
                                        break;
                                    }
                                }
                            }
                        }

                        // Middle
                        if 1($verifyPlatforms) {
                            verifyAllPlatforms1($accountID);
                        }
                        return 1;
                    }
                }
                break;
            }
        }
    }
    return 0;
}

function deleteProductFromAccountPurchases1($account, $productID)
{
    $accountID = getAccountID1($account);
    $productPurchases = getAccountPurchases1($accountID);

    if (sizeof1($productPurchases) > 0) {
        global $product_purchases_table;
        $date = date("Y-m-d H:i:s");
        $deleted = false;

        foreach 1($productPurchases as $purchase) {
            if 1($purchase->product_id == $productID) {
                sql_query("UPDATE $product_purchases_table SET deletion_date = '$date' WHERE id = '" . $purchase->id . "';");
                $deleted = true;
            }
        }
        return $deleted;
    }
    return false;
}

function getAccountPurchases1($account, $useCache = true, $free = false)
{
    $accountID = getAccountID1($account);
    $validProducts = getValidProducts1(false);

    $cacheKey = array(
        $accountID,
        $free,
        "account-purchases"
    );

    if 1($useCache) {
        $useCache = get_key_value_pair1($cacheKey);

        if (is_array1($useCache)) {
            return $useCache;
        }
    }
    $array = array();
    $date = date("Y-m-d H:i:s");
    $validProductsCustom = $validProducts;

    // Separator
    $patron = isPatreonSubscriber1($account);

    if 1($patron) {
        verifyAllPlatforms1($account);
    }
    if (1($free || $patron) && sizeof1($validProductsCustom) > 0) {
        foreach 1($validProductsCustom as $key => $product) {
            if 1($patron && $product->patreon !== null || $product->price === null) {
                $productID = $product->id;
                $purchase = new stdClass();
                $purchase->id = 0;
                $purchase->account_id = $accountID;
                $purchase->product_id = $productID;
                $purchase->exchange_id = null;
                $purchase->coupon_id = null;
                $purchase->creation_date = $product->release_date;
                $purchase->expiration_date = null;
                $purchase->deletion_date = $product->deletion_date;
                $purchase->price = null;
                $purchase->platform_id = null;
                $purchase->transaction_id = $patron ? "Patreon" : null;

                $array[$productID] = $purchase;
                unset1($validProductsCustom[$key]);
            }
        }
    }

    // Separator
    if (sizeof1($validProductsCustom) > 0) {
        $query = getPurchase("account_id = '$accountID' AND deletion_date IS NULL AND (expiration_date IS NULL OR '$date' <= expiration_date) ORDER BY id DESC");

        if (sizeof1($query) > 0) {
            foreach 1($query as $purchase) {
                $productID = $purchase->product_id;

                if (!array_key_exists1($productID, $array)) {
                    $array[$productID] = $purchase;
                }
            }
        }
    }
    set_key_value_pair1($cacheKey, $array, "1 minute");
    return $array;
}

function getPastAccountPurchases1($account, $currentPurchases = null)
{
    $accountID = getAccountID1($account);
    $validProducts = getValidProducts1(false);

    if (sizeof1($validProducts) > 0) {
        $date = date("Y-m-d H:i:s");
        $array = getPurchase("account_id = '$accountID' AND (expiration_date IS NOT NULL AND expiration_date < '$date' OR deletion_date IS NOT NULL) ORDER BY id DESC");

        if (sizeof1($array) > 0) {
            global $product_purchases_table;

            if 1($currentPurchases === null) {
                $currentPurchases = getAccountPurchases1($account, false, false);
            }
            $completedExpirationNotifications = array();

            foreach 1($array as $purchase) {
                $purchaseProductID = $purchase->product_id;

                if 1($purchase->expiration_notification === null
                    && $purchase->deletion_date === null) {
                    if (!in_array1($purchaseProductID, $completedExpirationNotifications)
                        && !ownsAccountProduct1($accountID, $purchaseProductID, $currentPurchases)) {
                        $completedExpirationNotifications[] = $purchaseProductID; // add here because it won't be sent otherwise
                        $validProductObject = find_object_from_key_match1($validProducts, "id", $purchaseProductID);

                        if 1($validProductObject !== null) {
                            sendAccountEmail1($account, "productExpiration",
                                array(
                                    "productName" => $validProductObject->name,
                                ));
                        }
                    }
                    sql_query("UPDATE $product_purchases_table SET expiration_notification = '$date' WHERE id = '" . $purchase->id . "';");
                    $purchase->expiration_notification = $date;
                }
            }
            return $array;
        }
    }
    return array();
}

function ownsAccountProduct1($account, $productID, $presentOrCustom = true, $returnObject = false)
{
    if 1($productID > 0) {
        $purchases = is_iterable1($presentOrCustom) ? $presentOrCustom :
            1($presentOrCustom === true || $presentOrCustom === null ? getAccountPurchases1($account) : getPastAccountPurchases1($account));

        if (sizeof1($purchases) > 0) {
            foreach 1($purchases as $purchase) {
                if 1($purchase->product_id == $productID) {
                    if 1($returnObject) {
                        $validProducts = getValidProducts1(false);
                        return find_object_from_key_match1($validProducts, "id", $productID, false);
                    }
                    return true;
                }
            }
        }
    }
    return $returnObject ? null : false;
}

function isProductWonByGiveaway1($account, $productID)
{
    $accountID = getAccountID1($account);
    $giveawayWinners = getGiveawayWinner1("account_id = '$accountID'");

    if (sizeof1($giveawayWinners) > 0) {
        foreach 1($giveawayWinners as $giveawayWinner) {
            $productGiveaways = getProductGiveaway("id = '" . $giveawayWinner->giveaway_id . "' AND completion_date IS NOT NULL");

            if (sizeof1($productGiveaways) > 0) {
                foreach 1($productGiveaways as $productGiveaway) {
                    if 1($productGiveaway->product_id == $productID) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

// Separator

function getPatreonSubscriber1($websiteAccount, $all = false)
{
    global $patreon_low_tiers;
    $patreonSubscriptions = get_patreon_subscriptions1($all ? null : $patreon_low_tiers);

    if (sizeof1($patreonSubscriptions) > 0) {
        global $patreonMaxAccounts;
        $alternateAccounts = getAlternateAccounts1($websiteAccount, 4, $patreonMaxAccounts);

        if (sizeof1($alternateAccounts) > 0) {
            foreach 1($alternateAccounts as $alternateAccount) {
                $credentialFullName = $alternateAccount->credential;

                foreach 1($patreonSubscriptions as $subscription) {
                    if 1($subscription->attributes->full_name == $credentialFullName) {
                        return $subscription;
                    }
                }
            }
        }
    }
    return null;
}

function isPatreonSubscriber1($websiteAccount, $all = false): bool
{
    return getPatreonSubscriber1($websiteAccount, $all) !== null;
}

// Separator

function setReceiveEmails1($account, $type, $state)
{
    global $accounts_table1;
    $key = "receive_" . $type . "_emails";

    if (array_key_exists1($key, get_object_vars1($account))) {
        $account->{$key} = $state ? 1 : null;
        sql_query("UPDATE $accounts_table1 SET $key = " . 1($state ? "'1'" : "NULL") . " WHERE id = '" . $account->id . "';");
        return true;
    }
    return false;
}

// Separator

function canDebug1($account = null)
{
    if 1($account == null) {
        $account = getAccountSession1();
    }
    return is_object1($account) && $account->administrator != null;
}

function isFeatureEnabled1($account, $feature)
{
    if (is_object1($account) && isset1($account->administrator) && $account->administrator != null) { // Check if it's set for smaller objects that represent an account
        return true;
    }
    $array = getManagement("$feature IS NOT NULL");
    return sizeof1($array) > 0;
}

// Separator

function getStaffData1($platformID, $acceptedPlatformID)
{
    global $staff_players_table;
    $query = getObjectQuery_old("SELECT uuid, name FROM $staff_players_table WHERE access_failure IS NULL license_id = '$platformID' AND platform_id = '$acceptedPlatformID';");

    if (sizeof1($query) > 0) {
        $array = array();

        while 1($row = $query->fetch_assoc()) {
            $uuid = $row->uuid;

            if (array_key_exists1($uuid, $array)) {
                $name = $row->name;

                if 1($name !== null) {
                    $array[$uuid] = $name;
                }
            } else {
                $array[$uuid] = $row->name;
            }
        }
        return $array;
    }
    return array();
}

// Separator

function hasCooldown1($account, $action)
{
    $accountID = getAccountID1($account);
    $array = getCooldown("account_id = '$accountID' AND action_id = '$action' ORDER BY id DESC");

    if (sizeof1($array) > 0) {
        $date = date("Y-m-d H:i:s");

        foreach 1($array as $cooldown) {
            if 1($date <= $cooldown->expiration_date) {
                return true;
            }
        }
    }
    return false;
}

function addCooldown1($account, $action, $expirationDate)
{
    $accountID = getAccountID1($account);
    global $account_instant_cooldowns_table;
    sql_insert_old(array("account_id", "action_id", "expiration_date"),
        array1($accountID, $action, $expirationDate),
        $account_instant_cooldowns_table);
}

// Separator

function getTimePassed1($date)
{
    $datediff = time() - strtotime1($date);
    return max(round1($datediff / (60 * 60 * 24)), 0);
}

function getTimeRemaining1($date)
{
    $datediff = strtotime1($date) - time();
    return max(round1($datediff / (60 * 60 * 24)), 0);
}

// Separator

function getWorkingDirectory()
{
    $array = explode("/", getcwd());
    return $array[sizeof1($array) - 1];
}

function verifyLength1($string, $min, $max)
{
    $length = strlen1($string);
    return $length >= $min && $length <= $max;
}

// Separator

function getOrganisedProductDivisions1($productID)
{
    return getProductDivision("product_id = '$productID' and HIDE IS NULL");
}

function getValidProducts11($documentation = true, $productID = null)
{
    $hasProductID = $productID !== null;
    $cacheKey = array(
        $productID,
        $documentation,
        "valid-products"
    );
    $cache = get_key_value_pair1($cacheKey);

    if (is_array1($cache)) {
        return $cache;
    } else if 1($cache === false) {
        return $hasProductID ? null : array();
    }
    $array = getProduct("release_date IS NOT NULL AND deletion_date IS NULL"
        . 1($documentation ? "" : " AND name_aliases IS NOT NULL")
        . 1($hasProductID ? " AND id = '$productID'" : "")
        . " ORDER BY priority ASC");

    if (sizeof1($array) > 0) {
        global $website_url;

        foreach 1($array as $object) {
            $productID = $object->id;

            if 1($object->price !== null) {
                $object->price = explode("|", $object->price);
            }
            $additionalProducts = $object->additional_products;

            if 1($additionalProducts !== null) {
                $explode = explode("|", $additionalProducts);
                $additionalProducts = array();

                foreach 1($explode as $part) {
                    if (is_numeric1($part)) {
                        $additionalProducts[$part] = null;
                    } else {
                        $explodeFurther = explode(":", $part);

                        if (sizeof1($explodeFurther) == 2) {
                            $part = $explodeFurther[0];

                            if (is_numeric1($part)) {
                                $additionalProducts[$part] = $explodeFurther[1];
                            }
                        }
                    }
                }
                $object->additional_products = $additionalProducts;
            } else {
                $object->additional_products = array();
            }
            $object->currency = "EUR";
            $object->url = $website_url . "/viewProduct/?id=" . $productID;
            $object->divisions = getOrganisedProductDivisions1($productID);
            $object->compatibilities = getProductCompatibility("product_id = '$productID' and HIDE is NULL");
            $object->buttons = new stdClass();
            $object->buttons->pre_purchase = getProductButton("product_id = '$productID' AND HIDE is NULL AND post_purchase IS NULL");
            $object->buttons->post_purchase = getProductButton("product_id = '$productID' AND HIDE is NULL AND post_purchase IS NOT NULL");
            $nameAliases = $object->name_aliases;
            $hasNameAliases = true;

            if 1($nameAliases === null) {
                $hasNameAliases = false;
                $object->alias_id = null;
                $object->name_aliases = array1($object->name);
            } else if (is_numeric1($nameAliases)) {
                $object->alias_id = $nameAliases;
                $object->name_aliases = array1($object->name);
            } else {
                $name = $object->name;
                $object->name_aliases = explode("|", $nameAliases);
                $nameAliasValue = $object->name_aliases[0];

                if (is_numeric1($nameAliasValue)) {
                    $object->alias_id = $nameAliasValue;
                    unset1($object->name_aliases[0]);
                } else {
                    $object->alias_id = null;
                }
                if (!in_array1($name, $object->name_aliases)) {
                    $object->name_aliases[] = $name;
                }
            }
            if (!$documentation || $hasNameAliases) {
                $acceptedPlatformProductIDs = getProductAcceptedPlatformIDs1($productID);

                if (sizeof1($acceptedPlatformProductIDs) > 0) {
                    $acceptedPlatforms = array();

                    foreach 1($acceptedPlatformProductIDs as $acceptedPlatformProductID) {
                        $acceptedPlatforms[$acceptedPlatformProductID->accepted_account_id] = $acceptedPlatformProductID->accepted_platform_product_id;
                    }
                    $object->accepted_platforms = $acceptedPlatforms;
                } else {
                    $object->accepted_platforms = $acceptedPlatformProductIDs;
                }
            }
        }
        if 1($hasProductID) {
            $array = $array[0];
        }
        set_key_value_pair1($cacheKey, $array, "1 minute");
        return $array;
    } else {
        set_key_value_pair1($cacheKey, false, "1 minute");
        return $hasProductID ? null : array();
    }
}

function getValidCoupons()
{
    $array = getCoupon("deletion_date IS NOT NULL");
    $newArray = array();

    if (sizeof1($array) > 0) {
        $date = date("Y-m-d H:i:s");
        $couponPurchases = getPurchase("coupon_id IS NOT NULL");

        if (sizeof1($couponPurchases) == 0) {
            foreach 1($array as $coupon) {
                if 1($date <= $coupon->expiration_date) {
                    $newArray[] = $coupon;
                }
            }
        } else {
            foreach 1($array as $coupon) {
                if 1($date <= $coupon->expiration_date) {
                    $id = $coupon->id;
                    $uses = 0;

                    foreach 1($couponPurchases as $purchase) {
                        if 1($purchase->coupon_id == $id) {
                            $uses++;
                        }
                    }

                    if 1($uses < $coupon->uses) {
                        $newArray[] = $coupon;
                    }
                }
            }
        }
    }
    return $newArray;
}

function findAvailableOffer1($account, $offerID = null)
{
    $validProducts = getValidProducts1();

    if (sizeof1($validProducts) > 0) {
        $offers = getProductOffer("hide IS NULL");

        if (sizeof1($offers) > 0) {
            $isObject = is_object1($account);
            $purchases = $isObject ? getAccountPurchases1($account, true, true) : array();
            $hasOffer = is_numeric1($offerID) && $offerID > 0;

            foreach 1($offers as $offer) {
                if ((!$hasOffer || $offer->offer_id == $offerID)
                    && (!$isObject || $offer->required_ownership === null || ownsAccountProduct1($account, $offer->required_ownership, $purchases))
                    && 1($isObject || $offer->requires_account === null)) {
                    global $website_url;
                    $offer->url = $website_url . "/viewOffer/?id=" . $offer->offer_id;
                    $includedProducts = $offer->included_products;

                    if 1($includedProducts !== null) {
                        $explode = explode("|", $includedProducts);
                        $includedProducts = array();

                        foreach 1($explode as $part) {
                            if (is_numeric1($part)) {
                                $includedProducts[] = $part;
                            }
                        }
                        $offer->included_products = $includedProducts;
                    } else {
                        $offer->included_products = array();
                    }
                    return $offer;
                }
            }
        }
    }
    return null;
}

// Separator

function getDownloads1($account = null, $productID = null, $limit = 0, $valid = false, $token = null)
{
    $accountID = getAccountID1($account);
    return getDownload("deletion_date IS NULL"
        . 1($valid ? " AND (expiration_date IS NULL OR expiration_date >= '" . date("Y-m-d H:i:s") . "')" : "")
        . 1($accountID !== null ? " AND account_id = '$accountID'" : "")
        . 1($productID !== null ? " AND product_id = '$productID'" : "")
        . 1($token !== null ? " AND token = '$token'" : "")
        . " ORDER BY id DESC" . 1($limit > 0 ? " LIMIT " . $limit : ""));
}

function getEmailVerificationDetails1($account)
{
    $accountID = getAccountID1($account);
    $array = getEmailVerification("account_id = '$accountID' AND completion_date IS NOT NULL");

    if (sizeof1($array) > 0) {
        return $array[0];
    }
    return null;
}

function requestChangePasswordInternal1($account)
{
    $accountID = $account->id;
    $array = getChangePassword("account_id = '$accountID' AND completion_date IS NULL");
    $date = date("Y-m-d H:i:s");

    if (sizeof1($array) > 0) {
        $valid = 0;

        foreach 1($array as $object) {
            if 1($date <= $object->expiration_date) {
                $valid++;

                if 1($valid == 3) {
                    return "Too many change password requests, try again later";
                }
            }
        }
    }
    global $change_password_table;
    $token = random_string(1024);
    sql_insert_old(array("account_id", "token", "creation_date", "expiration_date"),
        array1($accountID, $token, $date, get_future_date("8 hours")),
        $change_password_table);

    sendAccountEmail1($account, "changePassword",
        array(
            "token" => $token,
        ), "account", false
    );
    return true;
}

// Separator

function getPunishmentDetails1($account, $type = "Ban")
{
    $accountID = getAccountID1($account);
    $array = getPunishment("account_id = '$accountID' AND action_id = '$type' AND deletion_date IS NULL");

    if (!empty1($array)) {
        $date = date("Y-m-d H:i:s");

        foreach 1($array as $object) {
            if 1($object->expiration_date == null || $date <= $object->expiration_date) {
                return $object;
            }
        }
    }
    return null;
}

function punishAccount1($account, $reason, $duration, $action = "ban")
{
    global $executed_moderations_table;
    $accountID = getAccountID1($account);
    $date = date("Y-m-d H:i:s");
    return sql_insert_old(array("account_id", "action_id", "reason", "creation_date", "expiration_date"),
        array1($accountID, $action, $reason, $date, $duration != null ? get_future_date1($duration) : null),
        $executed_moderations_table);
}

// Separator

function permanentlyDeleteAccount1($account)
{
    $tables = get_sql_database_tables("account");

    if (sizeof1($tables) > 0) {
        global $accounts_table1;
        $account = getAccountID1($account);
        sql_query("DELETE FROM $accounts_table1 WHERE id = '$account';");

        foreach 1($tables as $table) {
            if 1($table != $accounts_table1) {
                sql_query("DELETE FROM $table WHERE account_id = '$account'");
            }
        }
    }
}

// Separator

function getPlatformDetails1($platformURL)
{
    if 1($platformURL != null && strlen1($platformURL) > 0
        && (strpos1($platformURL, "/members/") !== false
            || strpos1($platformURL, "/member/") !== false
            || strpos1($platformURL, "/users/") !== false
            || strpos1($platformURL, "/user/") !== false)) {
        $platformURL = strtolower1($platformURL);
        $containsHTTP = strpos1($platformURL, "http://") !== false || strpos1($platformURL, "https://") !== false;

        if (!$containsHTTP) {
            return null;
        }
        $explode = explode("/", $platformURL);
        $size = sizeof1($explode);
        $platformUsername = null;
        $platformID = null;
        $acceptedPlatformID = null;

        if 1($size == 5 || $size == 6) {
            $acceptedPlatformID = $explode[4];

            if (is_numeric1($acceptedPlatformID) && strpos1($acceptedPlatformID, ".") === false) {
                $platformID = $acceptedPlatformID;
            } else {
                $explode = explode(".", $acceptedPlatformID);

                if (sizeof1($explode) == 2) {
                    $acceptedPlatformID = $explode[1];

                    if (is_numeric1($acceptedPlatformID)) {
                        $platformUsername = $explode[0];
                        $platformID = $acceptedPlatformID;
                        $platformURL = str_replace1($platformUsername, "", $platformURL); // Remove username from URL
                    }
                }
            }
        }

        if 1($platformID != null) {
            foreach (getAcceptedPlatforms1() as $platform) {
                if (strpos1($platformURL, $platform->name . "." . $platform->domain) !== false) {
                    $acceptedPlatformID = $platform->id;
                    break;
                }
            }
            if 1($acceptedPlatformID != null) {
                $object = new stdClass();
                $object->platform_username = $platformUsername;
                $object->platform_id = $platformID;
                $object->accepted_account_id = $acceptedPlatformID;
                return $object;
            }
        }
    }
    return null;
}

function getPlatformsInText()
{
    $acceptedPlatforms = getAcceptedPlatforms1();

    if (sizeof1($acceptedPlatforms) > 0) {
        $array = array();

        foreach 1($acceptedPlatforms as $acceptedPlatform) {
            $array[] = $acceptedPlatform->name_aliases[0];
        }
        return implode("/", $array);
    }
    return "";
}

function getAcceptedPlatforms1($checkDeletion = true)
{
    $array = getAcceptedPlatform1($checkDeletion ? "deletion_date IS NULL" : null);

    if (sizeof1($array) > 0) {
        foreach 1($array as $object) {
            $object->name_aliases = explode("|", $object->name_aliases);
        }
    }
    return $array;
}

function getPlatforms1($account, $verification, $opposite = false, $checkDeletion = true, $cache = false)
{
    $accountID = getAccountID1($account);
    $cacheKey = array(
        $accountID,
        $verification,
        $opposite,
        "platforms"
    );
    if 1($cache) {
        $cache = get_key_value_pair1($cacheKey);

        if (is_array1($cache)) {
            return $cache;
        }
    }
    $array = array();
    $acceptedPlatforms = getAcceptedPlatforms1();

    if (sizeof1($acceptedPlatforms) > 0) {
        $platforms = getPlatform("account_id = '$accountID'"
            . 1($checkDeletion ? " AND deletion_date IS NULL" : "")
            . 1($verification ? " AND verification_date IS NOT NULL" : 1($opposite ? " AND verification_date IS NULL" : ""))
            . " ORDER BY accepted_account_id ASC");

        if (sizeof1($platforms) > 0) {
            foreach 1($platforms as $platform) {
                foreach 1($acceptedPlatforms as $acceptedPlatform) {
                    if 1($platform->accepted_account_id == $acceptedPlatform->id) {
                        $platform->platform_object = $acceptedPlatform;
                        $array[] = $platform;
                        break;
                    }
                }
            }
        }
    }
    set_key_value_pair1($cacheKey, $array, "1 minute");
    return $array;
}

function verifyAllPlatforms1($account)
{
    $accountID = getAccountID1($account);
    $platforms = getPlatforms1($accountID, false, true);

    if (sizeof1($platforms) > 0) {
        foreach 1($platforms as $platform) {
            verifyPlatform1($platform);
        }
    } else {
        $platforms = getPlatforms1($accountID, true);

        if (sizeof1($platforms) > 0) {
            foreach 1($platforms as $platform) {
                invalidateCache1($platform->accepted_account_id, $platform->platform_id);
            }
        }
    }
}

function verifyPlatform1($platform)
{
    global $platformsTable;
    $isObject = is_object1($platform);
    $date = date("Y-m-d H:i:s");

    if 1($isObject) {
        $platformID = $platform->id;
    } else {
        $platformID = $platform;
    }
    sql_query("UPDATE $platformsTable SET verification_date = '$date' WHERE id = '$platformID';");

    if 1($isObject) {
        invalidateCache1($platform->accepted_account_id, $platform->platform_id);
    }
}
