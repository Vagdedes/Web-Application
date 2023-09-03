<?php

function addFormInput($type, $key, $preview)
{
    if (is_array($preview)) {
        if (sizeof($preview) == 0) {
            $preview[] = "Empty";
        }
        echo "<input list='$key' name='$key' placeholder='$key'>";
        echo "<datalist id='$key'>";

        foreach ($preview as $content) {
            echo "<option value='$content'>";
        }
        echo "</datalist><br>";
    } else {
        echo "<input type='$type' name='$key' placeholder='$preview' style='margin: 0; padding: 0;'><br>";
    }
}

function addFormSubmit($key, $preview)
{
    echo "<input type='submit'" . ($key == null ? "" : " name='$key' ") . "value='$preview' style='margin: 0; padding: 0;'><br>";
}

function createForm($method, $space, $url = null)
{
    echo ($space ? "<p>" : "") . "<form method='$method'" . ($url == null ? "" : " action='$url' ") . "style='margin: 0; padding: 0;'>";
}

function endForm()
{
    echo "</form>";
}

// Separator
if (is_private_connection()) {
    $licenseID = explode(".", get_form_get("id"));
    $licenseID = preg_replace("/[^0-9]/", "", $licenseID[sizeof($licenseID) > 1 ? 1 : 0]);
    $platformID = get_form_get("platform");
    $data = get_form_post("data");
    $hasForm = !empty($_POST);

    if (!empty($data)) {
        header('Content-type: Application/JSON');
        echo base64_decode($data);
        return;
    }
    $informationLimiter = 15;

    if (is_numeric($licenseID)) {
        $licenseID = (int)$licenseID;
    }
    $hasWebsiteAccount = false;
    $hasPurchases = false;
    $websiteAccount = null;
    $date = get_current_date();
    $specificationsTableIgnoredKeys = array("id", "ip_address", "port");

    if (!$hasForm) {
        // invalidate cache
    }
    $verificationsTableQuery = getObjectQuery_old("SELECT * FROM $verifications_table WHERE "
        . "license_id = '$licenseID' AND (platform_id IS NULL OR platform_id = '$platformID')"
        . " ORDER BY last_access_date DESC LIMIT " . ($hasForm ? 1 : 10000) . ";");
    $websiteAccount = findAccountByPlatform1($licenseID, $platformID, true);
    $connectionCountQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $connection_count_table WHERE license_id = '$licenseID' AND (platform_id = '$platformID' OR platform_id IS NULL);");
    $licenseManagementQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $license_management_table WHERE platform_id = '$platformID';");
    $platformPurchases = getTotalPurchases1($platformID, $licenseID, false);
    $patreonSubscriber = $hasForm || !$hasWebsiteAccount ? null : getPatreonSubscriber1($websiteAccount);

    if (sizeof($platformPurchases) > 0) {
        $hasPurchases = true;

        foreach ($platformPurchases as $platformPurchase) {
            $platformPurchase->verification = isVerified($platformID, $licenseID, null, $platformPurchase->product_id, null);
        }
    }
    $specificationsTableQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $server_specifications_table;");
    $punishedPlayersQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $punished_players_table WHERE license_id = '$licenseID' AND platform_id = '$platformID' ORDER BY id DESC;");
    $automaticConfigurationChangesQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $configuration_changes_table WHERE license_id = '$licenseID' AND platform_id = '$platformID';");
    $disabledDetectionsQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $disabled_detections_table WHERE license_id = '$licenseID' AND platform_id = '$platformID';");
    $customerSupportQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $customer_support_table WHERE license_id = '$licenseID' AND platform_id = '$platformID';");
    $customerSupportCommandsQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $customer_support_commands_table WHERE license_id = '$licenseID' AND platform_id = '$platformID';");
    $staffPlayersQuery = $hasForm ? null : getObjectQuery_old("SELECT * FROM $staff_players_table WHERE license_id = '$licenseID' AND (platform_id = '$platformID' OR platform_id IS NULL);");
    $userObject = new stdClass();
    $filesArray = array();
    $connectionCountArray = array();

    if ($hasWebsiteAccount) {
        $alternateAccounts = getAlternateAccounts1($websiteAccount);

        foreach ($alternateAccounts as $alternateAccountRow) {
            unset($alternateAccountRow->account_id);
        }
    } else {
        $alternateAccounts = null;
    }
    $userObject->license = new stdClass();
    $userObject->license->platform_id = $licenseID;
    $userObject->license->accepted_account_id = $platformID;
    $userObject->license->account = new stdClass();
    $userObject->license->account->patreon = $patreonSubscriber;
    $userObject->license->account->information = $hasWebsiteAccount ? $websiteAccount : null;
    $userObject->license->account->alternate_accounts = $alternateAccounts;

    $userObject->verifications = new stdClass();
    $userObject->verifications->amount = 0;
    $userObject->verifications->successes = 0;
    $userObject->verifications->dismissed = 0;
    $userObject->verifications->failures = new stdClass();
    $userObject->verifications->failures->amount = 0;
    $userObject->verifications->failures->list = array();
    $userObject->verifications->list = array();

    $userObject->managements = new stdClass();
    $userObject->managements->amount = 0;
    $userObject->managements->list = array();
    $userObject->software = new stdClass();
    $presentSoftware = $userObject->software->present = new stdClass();
    $presentSoftware->amount = 0;
    $presentSoftware->list = array();
    $pastSoftware = $userObject->software->past = new stdClass();
    $pastSoftware->amount = 0;
    $pastSoftware->list = array();

    $userObject->connectionCount = new stdClass();
    $userObject->connectionCount->amount = 0;
    $userObject->connectionCount->list = array();

    $userObject->servers = new stdClass();
    $userObject->servers->amount = 0;
    $userObject->servers->list = array();

    $userObject->staff = new stdClass();
    $userObject->staff->amount = 0;
    $userObject->staff->list = array();
    $userObject->staff->nameless = array();

    $userObject->customerSupport = new stdClass();
    $userObject->customerSupport->amount = is_array($customerSupportQuery) ? sizeof($customerSupportQuery) : 0;
    $userObject->customerSupport->list = array();

    $userObject->customerSupportCommands = new stdClass();
    $userObject->customerSupportCommands->amount = is_array($customerSupportCommandsQuery) ? sizeof($customerSupportCommandsQuery) : 0;
    $userObject->customerSupportCommands->list = array();

    $userObject->disabledDetections = new stdClass();
    $userObject->disabledDetections->amount = 0;
    $userObject->disabledDetections->list = array();

    $userObject->automaticConfigurationChanges = new stdClass();
    $userObject->automaticConfigurationChanges->amount = is_array($automaticConfigurationChangesQuery) ? sizeof($automaticConfigurationChangesQuery) : 0;
    $userObject->automaticConfigurationChanges->list = $automaticConfigurationChangesQuery;

    $userObject->punishedPlayers = new stdClass();
    $userObject->punishedPlayers->amount = is_array($punishedPlayersQuery) ? sizeof($punishedPlayersQuery) : 0;
    $userObject->punishedPlayers->list = array();

    // Separator

    if (!empty($verificationsTableQuery)) {
        foreach ($verificationsTableQuery as $verificationsTableRow) {
            $ipAddress = $verificationsTableRow->ip_address;
            $port = $verificationsTableRow->port;

            if ($port !== null) {
                $foundObject = false;

                foreach ($userObject->servers->list as $key => $object) {
                    if ($object->ip_address == $ipAddress && $object->port == $port) {
                        $foundObject = true;
                        break;
                    }
                }

                if (!$foundObject) {
                    foreach ($userObject->servers->list as $key => $object) {
                        if ($object->ip_address == $ipAddress && $object->port == null) {
                            $foundObject = true;
                            $object->port = $port;

                            if (!empty($specificationsTableQuery)) {
                                foreach ($specificationsTableQuery as $specificationsTableRow) {
                                    if ($specificationsTableRow->ip_address == $ipAddress && $specificationsTableRow->port == $port) {
                                        $specificationsObject = new stdClass();

                                        foreach (get_object_vars($specificationsTableRow) as $key => $value) {
                                            if (!in_array($key, $specificationsTableIgnoredKeys)) {
                                                $specificationsObject->{$key} = $value;
                                            }
                                        }
                                        $object->specifications = $specificationsObject;
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }

                if (!$foundObject) {
                    $object = new stdClass();
                    $object->ip_address = $ipAddress;
                    $object->port = null;
                    $object->creation_date = $verificationsTableRow->creation_date;
                    $object->last_access_date = $verificationsTableRow->last_access_date;
                    $userObject->servers->list[] = $object;
                    $userObject->servers->amount++;

                    if (!empty($specificationsTableQuery)) {
                        foreach ($specificationsTableQuery as $specificationsTableRow) {
                            if ($specificationsTableRow->ip_address == $ipAddress && $specificationsTableRow->port == $port) {
                                $specificationsObject = new stdClass();

                                foreach (get_object_vars($specificationsTableRow) as $key => $value) {
                                    if (!in_array($key, $specificationsTableIgnoredKeys)) {
                                        $specificationsObject->{$key} = $value;
                                    }
                                }
                                $object->specifications = $specificationsObject;
                                break;
                            }
                        }
                    }
                }
            } else {
                $foundObject = false;

                foreach ($userObject->servers->list as $key => $object) {
                    if ($object->ip_address == $ipAddress) {
                        $foundObject = true;
                        break;
                    }
                }

                if (!$foundObject) {
                    $object = new stdClass();
                    $object->ip_address = $ipAddress;
                    $object->port = null;
                    $object->creation_date = $verificationsTableRow->creation_date;
                    $object->last_access_date = $verificationsTableRow->last_access_date;
                    $object->specifications = null;
                    $userObject->servers->list[] = $object;
                    $userObject->servers->amount++;
                }
            }

            // Separator

            if ($verificationsTableRow->access_failure === null) {
                $userObject->verifications->successes++;
            } else {
                $userObject->verifications->failures->amount++;

                if (sizeof($userObject->verifications->failures->list) < $informationLimiter) {
                    $failureObject = new stdClass();
                    $failureObject->access_failure = $verificationsTableRow->access_failure;
                    $failureObject->ip_address = $ipAddress;
                    $failureObject->file_id = $verificationsTableRow->file_id;
                    $failureObject->product_id = $verificationsTableRow->product_id;
                    $arrayKey = implode("-", array_values(get_object_vars($failureObject)));
                    $failureObject->date = $verificationsTableRow->last_access_date;

                    if (!array_key_exists($arrayKey, $userObject->verifications->failures->list)) {
                        $userObject->verifications->failures->list[$arrayKey] = $failureObject;
                    }
                }
            }

            if ($verificationsTableRow->dismiss == 1) {
                $userObject->verifications->dismissed++;
            }

            $userObject->verifications->amount++;
        }
        $userObject->verifications->failures->list = array_values($userObject->verifications->failures->list);
        $serverListSize = sizeof($userObject->servers->list);

        if ($serverListSize > 10) {
            for ($i = 0; $i < ($serverListSize - $informationLimiter); $i++) {
                array_pop($userObject->servers->list);
            }
        }
    }

    // Separator

    if (!empty($licenseManagementQuery)) {
        foreach ($licenseManagementQuery as $licenseManagementRow) {
            unset($licenseManagementRow->id);
            $number = $licenseManagementRow->number;

            if ($licenseID == $number || in_array($number, $filesArray)) {
                $userObject->managements->list[] = $licenseManagementRow;
                $userObject->managements->amount++;
            }
        }
    }

    // Separator

    if (!empty($punishedPlayersQuery)) {
        $counter = 0;

        foreach ($punishedPlayersQuery as $punishedPlayersRow) {
            $counter++;
            $userObject->punishedPlayers->list[] = $punishedPlayersRow->uuid;

            if ($counter == $informationLimiter) {
                break;
            }
        }
    }

    // Separator

    if (!empty($customerSupportQuery)) {
        foreach ($customerSupportQuery as $customerSupportRow) {
            unset($customerSupportRow->id);
            unset($customerSupportRow->user_information);
            unset($customerSupportRow->software_information);
            $userObject->customerSupport->list[] = $customerSupportRow;
        }
    }

    // Separator

    if (!empty($customerSupportCommandsQuery)) {
        foreach ($customerSupportCommandsQuery as $customerSupportCommandsRow) {
            unset($customerSupportCommandsRow->id);
            $userObject->customerSupportCommands->list[] = $customerSupportCommandsRow;
        }
    }

    // Separator

    if (!empty($staffPlayersQuery)) {
        foreach ($staffPlayersQuery as $staffPlayersRow) {
            $uuid = $staffPlayersRow->uuid;
            $name = $staffPlayersRow->name;
            $foundObject = null;

            foreach ($userObject->staff->list as $playerObject) {
                if ($playerObject->uuid == $uuid) {
                    $foundObject = $playerObject;
                    break;
                }
            }

            if ($foundObject !== null) {
                if ($name !== null && !in_array($name, $foundObject->names)) {
                    $foundObject->names[] = $name;
                }
            } else {
                $playerObject = new stdClass();
                $playerObject->uuid = $uuid;
                $playerObject->names = $name !== null ? array($name) : array();
                $userObject->staff->amount++;

                if (sizeof($userObject->staff->list) < $informationLimiter) {
                    $userObject->staff->list[] = $playerObject;
                }
            }
        }
    }

    // Separator

    if (!empty($automaticConfigurationChangesQuery)) {
        foreach ($automaticConfigurationChangesQuery as $automaticConfigurationChangesRow) {
            unset($automaticConfigurationChangesRow->id);
        }
    }

    // Separator

    if (!empty($userObject->staff->list)) {
        foreach ($userObject->staff->list as $key => $object) {
            if (sizeof($object->names) == 0) {
                $userObject->staff->nameless[] = $object->uuid;
                unset($userObject->staff->list[$key]);
            }
        }
    }

    // Separator
    $disabledDetectionsArray = array();

    if (!empty($disabledDetectionsQuery)) {
        foreach ($disabledDetectionsQuery as $disabledDetectionsKey => $disabledDetectionsRow) {
            $detections = $disabledDetectionsRow->detections;

            foreach (explode(" ", $detections) as $detection) {
                $checkAndDetections = explode("|", $detection);
                $detectionsAmount = sizeof($checkAndDetections);

                if ($detectionsAmount >= 2) {
                    unset($disabledDetectionsRow->id);
                    $check = $checkAndDetections[0];
                    unset($checkAndDetections[0]);

                    $object = new stdClass();
                    $object->server_version = $disabledDetectionsRow->server_version;
                    $object->plugin_version = $disabledDetectionsRow->plugin_version;
                    $object->check = $check;
                    $object->detections = $checkAndDetections;
                    $userObject->disabledDetections->list[] = $object;
                    $userObject->disabledDetections->amount += ($detectionsAmount - 1);
                    $disabledDetectionsArray[strtolower($check)] = $checkAndDetections;
                } else {
                    unset($disabledDetectionsQuery[$disabledDetectionsKey]);
                }
            }
        }
    }

    // Separator

    if (!empty($connectionCountQuery)) {
        foreach ($connectionCountQuery as $connectionCountRow) {
            $connectionCountKey =
                $connectionCountRow->ip_address . "-"
                . $connectionCountRow->version . "-"
                . $connectionCountRow->product_id . "-"
                . $connectionCountRow->access_failure . "-"
                . $connectionCountRow->platform_id . "-"
                . $connectionCountRow->verification_requirement;

            if (array_key_exists($connectionCountKey, $connectionCountArray)) {
                $connectionCountArray[$connectionCountKey]->causes[$connectionCountRow->cause] = $connectionCountRow->count;
            } else {
                $product = $connectionCountRow->product_id;

                foreach ($valid_products as $validProductName => $validProductObject) {
                    if ($validProductObject->id == $product) {
                        $product = $validProductObject->name;
                        break;
                    }
                }
                unset($connectionCountRow->id);
                unset($connectionCountRow->license_id);
                unset($connectionCountRow->product_id);
                unset($connectionCountRow->date);
                $connectionCountRow->causes = array($connectionCountRow->cause => $connectionCountRow->count);
                unset($connectionCountRow->cause);
                unset($connectionCountRow->count);
                $connectionCountRow->product = $product;
                $connectionCountArray[$connectionCountKey] = $connectionCountRow;
            }
        }
        foreach ($connectionCountArray as $key => $value) {
            $userObject->connectionCount->list[] = $value;
        }
        $userObject->connectionCount->amount = sizeof($connectionCountArray);
    }

    // Separator
    if ($hasPurchases) {
        $platformPurchasesAmount = sizeof($platformPurchases);

        if ($platformPurchases > 0) {
            $presentSoftware->amount = $platformPurchasesAmount;
            $presentSoftware->list = $platformPurchases;
        }
    }

    // Separator
    $pastPlatformPurchases = getPastTotalPurchases1($platformID, $licenseID, $websiteAccount);
    $pastPlatformPurchasesAmount = sizeof($pastPlatformPurchases);

    if ($pastPlatformPurchasesAmount > 0) {
        $pastSoftware->amount = $pastPlatformPurchasesAmount;
        $pastSoftware->list = $pastPlatformPurchases;
    }

    if (get_form_get("type") == "data") {
        header('Content-type: Application/JSON');
        echo json_encode($userObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo "<style>
                    body {
                        overflow: auto;
                        font-family: Verdana;
                        background-size: 100%;
                        background-color: #212121;
                        color: #eee;
                    }
                    
                    form {
                        float: left;
                    }
                    
                    div {
                        margin-top: 300px;
                    }
                    
                    @media screen and (max-width: 1024px) {
                        form {
                            float: none;
                        }
                        
                        div {
                            margin-top: 0px;
                        }
                    }
                    </style>";

        if (false && !$hasForm) {
            $data = base64_encode(json_encode($userObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            echo "<script>

                    function openWindowWithPost(data) {
                        var form = document.createElement('form');
                        form.target = '_blank';
                        form.method = 'POST';
                        form.action = window.location.href; // URL
                        form.style.display = 'none';
                    
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'data';
                        input.value = data;
                        form.appendChild(input);
                            
                        document.body.appendChild(form);
                        form.submit();
                        document.body.removeChild(form);
                    }
                    
                    window.onload = function() {
                         openWindowWithPost('$data');
                    }
                    </script>";
        } else {
            echo get_text_list_from_iterable($userObject, 0, true);
        }
        $updateServerLimit = "updateServerLimit";
        $desiredServerLimit = max($userObject->servers->amount, 10);

        $verifyWebsiteAccount = "verifyWebsiteAccount";
        $deleteWebsiteAccount = "deleteWebsiteAccount";
        $punishWebsiteAccount = "punishWebsiteAccount";

        $addAlternateAccount = "addAlternateAccount";

        $addToManagement = "addToManagement";
        $removeFromManagement = "removeFromManagement";

        $addProduct = "addProduct";
        $removeProduct = "removeProduct";
        $modifyProduct = "modifyProduct";

        $exchangeProduct = "exchangeProduct";

        $addConfigurationChange = "addConfigurationChange";
        $removeConfigurationChange = "removeConfigurationChange";

        $addDisabledDetection = "addDisabledDetection";
        $removeDisabledDetection = "removeDisabledDetection";

        $addCustomerSupportCommand = "addCustomerSupportCommand";

        $executeAnticheatCorrection = "executeAnticheatCorrection";
        $anticheatCorrectionsQuery = getObjectQuery_old("SELECT * FROM panel.handledConfigurationChanges;");
        $hasAnticheatCorrections = sizeof($anticheatCorrectionsQuery) > 0;

        $queuePayPalTransaction = "queuePayPalTransaction";
        $failedPayPalTransaction = "failedPayPalTransaction";

        $listUserVerification = "listUserVerification";

        $sendDiscordWebhook = "sendDiscordWebhook";

        $resolveCustomerSupport = "resolveCustomerSupport";

        // Separator

        $validProductsArray = array();

        foreach ($valid_products as $validProductObject) {
            if (!in_array($validProductObject->name, $validProductsArray)) {
                $validProductsArray[] = $validProductObject->name;
            }
        }

        $validDiscordWebhooks = get_discord_plans();
        $discordWebhooksArray = array();

        if (sizeof($validDiscordWebhooks) > 0) {
            foreach ($validDiscordWebhooks as $discordWebhook) {
                $discordWebhooksArray[] = $discordWebhook->name;
            }
        }

        // Separator

        if ($hasForm) {
            foreach ($_POST as $postArgumentKey => $postArgument) {
                switch ($postArgumentKey) {
                    case $updateServerLimit:
                        manageLicense($platformID,
                            $licenseID,
                            null,
                            $managed_license_types[5],
                            $desiredServerLimit,
                            null,
                            null,
                            false);
                        break;
                    case $verifyWebsiteAccount:
                        if ($hasWebsiteAccount && $websiteAccount->verification_date == null) {
                            verifyAllPlatforms($websiteAccount->id);
                        }
                        break;
                    case $deleteWebsiteAccount:
                        if ($hasWebsiteAccount) {
                            $accountID = $websiteAccount->id;

                            if ($accountID == get_form_post("account_id")) {
                                permanentlyDeleteAccount($accountID);
                            }
                        }
                        break;
                    case $punishWebsiteAccount:
                        if ($hasWebsiteAccount) {
                            $reason = get_form_post("reason");
                            $duration = get_form_post("duration");

                            if (strlen($reason) > 0) {
                                punishAccount($websiteAccount->id, $reason, strlen($duration) == 0 ? null : $duration);
                            }
                        }
                        break;
                    case $addToManagement:
                        $product = get_form_post("product");
                        $productID = null;

                        if (strlen($product) > 0) {
                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $productID = $validProductObject->id;
                                    break;
                                }
                            }
                        }
                        $reason = get_form_post("reason");
                        $expirationDate = get_form_post("expiration_date");
                        manageLicense($platformID,
                            $licenseID,
                            $productID,
                            get_form_post("type"),
                            strlen($reason) > 0 ? $reason : null,
                            null,
                            strlen($expirationDate) > 0 ? $expirationDate : null,
                            false);
                        break;
                    case $removeFromManagement:
                        $product = get_form_post("product");
                        $productID = null;

                        if (strlen($product) > 0) {
                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $productID = $validProductObject->id;
                                    break;
                                }
                            }
                        }
                        manageLicense($platformID,
                            $licenseID,
                            $productID,
                            get_form_post("type"),
                            null,
                            null,
                            null,
                            false,
                            false,
                            false);
                        break;
                    case $addProduct:
                        $product = get_form_post("product");

                        if (strlen($product) > 0) {
                            $duration = get_form_post("duration");

                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $creationDate = get_form_post("creation_date");
                                    addProductToCorrectPurchases1($platformID,
                                        $licenseID,
                                        $validProductObject->id,
                                        null,
                                        strlen($creationDate) > 0 ? $creationDate : null,
                                        strlen($duration) > 0 ? $duration : null,
                                        $hasWebsiteAccount ? $websiteAccount : 1,
                                        strlen(get_form_post("email")) > 0,
                                        false,
                                        strlen(get_form_post("additional_products")) > 0);
                                    break;
                                }
                            }
                        }
                        break;
                    case $removeProduct:
                        if ($hasWebsiteAccount) {
                            $product = get_form_post("product");

                            if (strlen($product) > 0) {
                                foreach ($valid_products as $validProductName => $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        deleteProductFromCorrectPurchases($platformID, $licenseID, $validProductObject->id);
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    case $exchangeProduct:
                        $product = get_form_post("product");

                        if (strlen($product) > 0) {
                            $currentProductID = null;

                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $currentProductID = $validProductObject->id;
                                    break;
                                }
                            }

                            if ($currentProductID !== null) {
                                $replacementProduct = get_form_post("replacement_product");

                                foreach ($valid_products as $validProductName => $validProductObject) {
                                    if ($replacementProduct == $validProductObject->name) {
                                        exchangePurchase($platformID,
                                            $licenseID,
                                            $currentProductID,
                                            $validProductObject->id,
                                            strlen(get_form_post("email")) > 0,
                                            $websiteAccount);
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    case $modifyProduct:
                        $id = get_form_post("id");

                        if (is_numeric($id)) {
                            $key = get_form_post("key");

                            if (strlen($key) > 0) {
                                $value = get_form_post("value");

                                if (strlen($value) > 0) {
                                    if ($value !== "NULL") {
                                        $value = "'" . $value . "'";
                                    }
                                    $table = $hasWebsiteAccount ? $product_purchases_table : $account_purchases_table;
                                    sql_query("UPDATE $table SET $key = $value WHERE id = '$id';");
                                }
                            }
                        }
                        break;
                    case $sendDiscordWebhook:
                        $webhook = get_form_post("webhook");

                        if (sizeof($validDiscordWebhooks) > 0) {
                            foreach ($validDiscordWebhooks as $discordWebhook) {
                                if ($discordWebhook->name == $webhook) {
                                    send_discord_webhook_by_plan($discordWebhook->id);
                                    break;
                                }
                            }
                        }
                        break;
                    case $addAlternateAccount:
                        $acceptedAccounts = getAcceptedAccounts1();

                        if (sizeof($acceptedAccounts) > 0) {
                            $name = get_form_post("name");

                            if (strlen($name) > 0) {
                                foreach ($acceptedAccounts as $acceptedAccount) {
                                    if ($acceptedAccount->name == $name) {
                                        $information = get_form_post("information");

                                        if (strlen($information) > 0) {
                                            addAccount1($name, $information, $websiteAccount);
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    case $addConfigurationChange:
                        $product = get_form_post("product");
                        $productID = null;

                        if (strlen($product) > 0) {
                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $productID = $validProductObject->id;
                                    break;
                                }
                            }
                        }

                        if ($productID !== null) {
                            $everyone = get_form_post("everyone");
                            $version = get_form_post("version");
                            $file = get_form_post("file");
                            $value = get_form_post("value");
                            manageAutomaticConfigurationChange(
                                strlen($everyone) > 0 ? null : $licenseID,
                                $platformID,
                                is_numeric($version) ? $version : null,
                                strlen($file) > 0 ? $file : "checks",
                                get_form_post("option"),
                                strlen($value) > 0 ? $value : "false",
                                true,
                                $productID,
                                strlen(get_form_post("email")) > 0);
                        }
                        break;
                    case $removeConfigurationChange:
                        $product = get_form_post("product");
                        $productID = null;

                        if (strlen($product) > 0) {
                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $productID = $validProductObject->id;
                                    break;
                                }
                            }
                        }

                        if ($productID !== null) {
                            $everyone = get_form_post("everyone");
                            $file = get_form_post("file");
                            manageAutomaticConfigurationChange(
                                strlen($everyone) > 0 ? null : $licenseID,
                                $platformID,
                                null,
                                strlen($file) > 0 ? $file : "checks",
                                get_form_post("option"),
                                null,
                                false,
                                $productID,
                                strlen(get_form_post("email")) > 0);
                        }
                        break;
                    case $addDisabledDetection:
                        $check = get_form_post("check");
                        $detection = get_form_post("detection");

                        if (strlen($check) > 0 && strlen($detection) > 0) {
                            $everyone = get_form_post("everyone");
                            $pluginVersion = get_form_post("plugin_version");
                            $serverVersion = get_form_post("server_version");
                            manageDisabledDetection(
                                strlen($everyone) > 0 ? null : $licenseID,
                                $platformID,
                                is_numeric($pluginVersion) ? $pluginVersion : null,
                                is_numeric($serverVersion) ? $serverVersion : null,
                                $check,
                                $detection,
                                true,
                                strlen(get_form_post("email")) > 0);
                        }
                        break;
                    case $removeDisabledDetection:
                        $everyone = get_form_post("everyone");
                        $pluginVersion = get_form_post("plugin_version");
                        $serverVersion = get_form_post("server_version");
                        manageDisabledDetection(
                            strlen($everyone) > 0 ? $licenseID : null,
                            $platformID,
                            is_numeric($pluginVersion) ? $pluginVersion : null,
                            is_numeric($serverVersion) ? $serverVersion : null,
                            get_form_post("check"),
                            get_form_post("detection"),
                            false,
                            strlen(get_form_post("email")) > 0);
                        break;
                    case $addCustomerSupportCommand:
                        $product = get_form_post("product");
                        $productID = null;

                        if (strlen($product) > 0) {
                            foreach ($valid_products as $validProductName => $validProductObject) {
                                if ($product == $validProductObject->name) {
                                    $productID = $validProductObject->id;
                                    break;
                                }
                            }
                        }

                        if ($productID !== null) {
                            $user = get_form_post("user");
                            $functionality = get_form_post("functionality");
                            $hasUser = strlen($user) > 0;
                            $hasFunctionality = strlen($functionality) > 0;

                            if ($hasUser || $hasFunctionality) {
                                $version = get_form_post("version");
                                addCustomerSupportCommand(
                                    $platformID,
                                    $licenseID,
                                    $productID,
                                    is_numeric($version) ? $version : null,
                                    $hasUser ? $user : null,
                                    $hasFunctionality ? $functionality : null
                                );
                            }
                        }
                        break;
                    case $executeAnticheatCorrection:
                        if ($hasAnticheatCorrections) {
                            $correction = get_form_post("correction");
                            $correctionID = -1;

                            foreach ($anticheatCorrectionsQuery as $anticheatCorrectionsRow) {
                                $changeName = $anticheatCorrectionsRow->change_name;
                                $use = false;

                                if ($changeName !== null) {
                                    if ($changeName == $correction) {
                                        $correctionID = $anticheatCorrectionsRow->change_id;
                                        $use = true;
                                    }
                                } else if ($correctionID != 1 && $correctionID == $anticheatCorrectionsRow->change_id) {
                                    $use = true;
                                }

                                if ($use) {
                                    $purpose = getConnectionPurpose("automaticConfigurationChanges");

                                    if (is_object($purpose)) {
                                        $email = strlen(get_form_post("email")) > 0;

                                        foreach ($purpose->allowed_products as $allowedProductID) {
                                            manageAutomaticConfigurationChange($licenseID,
                                                $platformID,
                                                null,
                                                $anticheatCorrectionsRow->configuration_file,
                                                $anticheatCorrectionsRow->configuration_option,
                                                $anticheatCorrectionsRow->configuration_value,
                                                true,
                                                $allowedProductID,
                                                $email);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    case $queuePayPalTransaction:
                        $transactionID = get_form_post("id");

                        if (strlen($transactionID) > 0 && queue_paypal_transaction($transactionID)) {
                            invalidateCache1($platformID, $licenseID);
                        }
                        break;
                    case $failedPayPalTransaction:
                        $transactionID = get_form_post("id");

                        if (strlen($transactionID) > 0 && process_failed_paypal_transaction($transactionID)) {
                            invalidateCache1($platformID, $licenseID);
                        }
                        break;
                    case $listUserVerification:
                        $formIpAddress = get_form_post("ip_address");

                        if (is_ip_address($formIpAddress)) {
                            $product = get_form_post("product");

                            if (strlen($product) > 0) {
                                foreach ($valid_products as $validProductName => $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        sql_insert(
                                            $verifications_table,
                                            array(
                                                "ip_address" => $formIpAddress,
                                                "creation_date" => $date,
                                                "last_access_date" => $date,
                                                "platform_id" => $platformID,
                                                "product_id" => $validProductObject->id,
                                                "license_id" => $licenseID,
                                                "file_id" => $licenseID,
                                            )
                                        );
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                    case $resolveCustomerSupport:
                        $functionality = get_form_post("functionality");

                        if (strlen($functionality) > 0) {
                            resolveCustomerSupport($platformID, $licenseID, $functionality);
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        echo "<head>
                        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
                        <title>User Details</title>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>    
                        <meta name='description' content='User Details Panel'>
                      </head>
                      <body>";

        createForm("post", true);
        addFormSubmit(null, "Refresh Page");
        endForm();

        createForm("get", false, "https://vagdedes.com/contents/?path=minecraft/cloud/userDetails/");
        addFormInput("text", "platform", array_keys($allowed_platforms));
        addFormInput("text", "id", "ID");
        addFormSubmit(null, "Find User");
        endForm();

        createForm("post", true);
        addFormInput("text", "id", "Transaction ID");
        addFormSubmit($queuePayPalTransaction, "Queue PayPal Transaction");
        endForm();

        createForm("post", true);
        addFormInput("text", "id", "Transaction ID");
        addFormSubmit($failedPayPalTransaction, "Mark Failed PayPal Transaction");
        endForm();

        createForm("post", true);
        addFormInput("text", "ip_address", "IP Address");
        addFormInput("text", "product", $validProductsArray);
        addFormSubmit($listUserVerification, "List User Verification");
        endForm();

        if ($hasWebsiteAccount) {
            createForm("post", true);
            addFormInput("number", "account_id", "Type the account ID to verify");
            addFormSubmit($deleteWebsiteAccount, "Delete Website Account");
            endForm();

            createForm("post", true);
            addFormInput("text", "reason", "Reason");
            addFormInput("text", "duration", "Time Duration");
            addFormSubmit($punishWebsiteAccount, "Punish Website Account");
            endForm();

            $acceptedAccounts = getAcceptedAccounts1();

            if (sizeof($acceptedAccounts) > 0) {
                $acceptedAccountNames = array();

                foreach ($acceptedAccounts as $acceptedAccount) {
                    $acceptedAccountNames[] = $acceptedAccount->name;
                }
                createForm("post", true);
                addFormInput("text", "name", $acceptedAccountNames);
                addFormInput("text", "information", "Account Information");
                addFormSubmit($addAlternateAccount, "Add Alternate Account");
                endForm();
            }

            createForm("post", true);
            addFormSubmit($verifyWebsiteAccount, "Verify Website Account");
            endForm();

            if ($desiredServerLimit > 1) {
                createForm("post", false);
                addFormSubmit($updateServerLimit, "Update Server Limit To $desiredServerLimit");
                endForm();
            }
        } else if ($desiredServerLimit > 1) {
            createForm("post", true);
            addFormSubmit($updateServerLimit, "Update Server Limit To $desiredServerLimit");
            endForm();
        }

        createForm("post", true);
        addFormInput("text", "type", $managed_license_types);
        addFormInput("text", "product", $validProductsArray);
        addFormInput("text", "reason", "Reason");
        addFormInput("text", "expiration_date", "Expiration Date");
        addFormSubmit($addToManagement, "Add To Management");
        addFormSubmit($removeFromManagement, "Remove From Management");
        endForm();

        createForm("post", true);
        addFormInput("text", "product", $validProductsArray);
        addFormInput("text", "creation_date", "Creation Date");
        addFormInput("text", "duration", "Time Duration");
        addFormInput("number", "email", "Email");
        addFormInput("number", "additional_products", "Additional Products");
        addFormSubmit($addProduct, "Add Product");
        addFormSubmit($removeProduct, "Remove Product");
        endForm();

        if ($hasPurchases || $pastPlatformPurchasesAmount > 0) {
            $purchaseIDs = array();
            $purchaseKeys = array();

            if ($hasPurchases) {
                foreach ($platformPurchases as $purchase) {
                    $purchaseIDs[] = $purchase->product_id;

                    foreach (array_keys(get_object_vars($purchase)) as $purchaseKey) {
                        if (!in_array($purchaseKey, $purchaseKeys)) {
                            $purchaseKeys[] = $purchaseKey;
                        }
                    }
                }
            }
            if ($pastPlatformPurchasesAmount > 0) {
                foreach ($pastPlatformPurchases as $purchase) {
                    $purchaseIDs[] = $purchase->product_id;

                    foreach (array_keys(get_object_vars($purchase)) as $purchaseKey) {
                        if (!in_array($purchaseKey, $purchaseKeys)) {
                            $purchaseKeys[] = $purchaseKey;
                        }
                    }
                }
            }
            createForm("post", true);
            addFormInput("text", "id", $purchaseIDs);
            addFormInput("text", "key", $purchaseKeys);
            addFormInput("text", "value", "Value");
            addFormSubmit($modifyProduct, "Modify Product");
            endForm();
        }

        createForm("post", true);
        addFormInput("text", "product", $validProductsArray);
        addFormInput("text", "replacement_product", $validProductsArray);
        addFormInput("number", "email", "Email");
        addFormSubmit($exchangeProduct, "Exchange Product");
        endForm();

        createForm("post", true);
        addFormInput("number", "everyone", "Everyone");
        addFormInput("text", "product", $validProductsArray);
        addFormInput("text", "file", "File Name");
        addFormInput("text", "option", "Option Name");
        addFormInput("text", "value", "Option Value");
        addFormInput("number", "email", "Email");
        addFormSubmit($addConfigurationChange, "Add Configuration Change");
        addFormSubmit($removeConfigurationChange, "Remove Configuration Change");
        endForm();

        createForm("post", true);
        addFormInput("number", "everyone", "Everyone");
        addFormInput("text", "plugin_version", "Plugin Version");
        addFormInput("text", "server_version", "Server Version");
        addFormInput("text", "check", "Check");
        addFormInput("text", "detection", "Detection");
        addFormInput("number", "email", "Email");
        addFormSubmit($addDisabledDetection, "Add Disabled Detection");
        addFormSubmit($removeDisabledDetection, "Remove Disabled Detection");
        endForm();

        createForm("post", true);
        addFormInput("text", "product", $validProductsArray);
        addFormInput("text", "version", "Version");
        addFormInput("text", "user", "User");
        addFormInput("text", "functionality", "Functionality");
        addFormSubmit($addCustomerSupportCommand, "Add Customer Support Command");
        endForm();

        createForm("post", true);
        addFormInput("text", "functionality", "Functionality");
        addFormSubmit($resolveCustomerSupport, "Resolve Customer Support");
        endForm();

        $anticheatCorrections = array();
        $anticheatCorrectionsQuery = getObjectQuery_old("SELECT change_name FROM panel.handledConfigurationChanges;");

        if ($hasAnticheatCorrections) {
            foreach ($anticheatCorrectionsQuery as $anticheatCorrectionsRow) {
                $changeName = $anticheatCorrectionsRow->change_name;

                if ($changeName !== null) {
                    $anticheatCorrections[] = $changeName;
                }
            }
            createForm("post", true);
            addFormInput("text", "correction", $anticheatCorrections);
            addFormInput("number", "email", "Email");
            addFormSubmit($executeAnticheatCorrection, "Execute AntiCheat Correction");
            endForm();
        }

        if (sizeof($discordWebhooksArray) > 0) {
            createForm("post", true);
            addFormInput("text", "webhook", $discordWebhooksArray);
            addFormSubmit($sendDiscordWebhook, "Send Discord Webhook");
            endForm();
        }

        // Separator

        $customerSupport = getCustomerSupport($disabledDetectionsArray);

        if (!empty($customerSupport)) {
            echo "<div><p><b>Customer Support</b><p><ul>";

            foreach ($customerSupport as $customerSupportID => $customerSupportValues) {
                echo "<li>";
                echo $customerSupportID . "<br>";
                $customerSupportLicense = $customerSupportValues->license_id;
                $customerSupportPlatform = $customerSupportValues->platform_id;

                foreach ($customerSupportValues as $customerSupportKey => $customerSupportValue) {
                    if (is_array($customerSupportValue)) {
                        if (sizeof($customerSupportValue) <= 10
                            || $customerSupportLicense == $licenseID && $customerSupportPlatform == $platformID) {
                            echo $customerSupportKey . ":<ul>";

                            foreach ($customerSupportValue as $customerSupportChild) {
                                echo "<li>" . $customerSupportChild . "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo $customerSupportKey . ": <ul><li><a href='https://vagdedes.com/contents/?path=minecraft/cloud/userDetails&platform=$customerSupportPlatform&id=$customerSupportLicense'>Visit User</a></li></ul>";
                        }
                    } else if ($customerSupportValue !== null) {
                        echo $customerSupportKey . ": <ul><li>" . $customerSupportValue . "</li></ul>";
                    }
                }
                echo "</li><p>";
            }
            echo "</ul></div>";
        }

        // Separator
        echo "</body>";
    }
}