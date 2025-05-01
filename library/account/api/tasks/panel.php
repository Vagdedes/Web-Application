<?php
require_once '/var/www/.structure/library/base/requirements/account_systems.php';

if (is_private_connection()) {
    require_once '/var/www/.structure/library/base/form.php';

    function addFormInput(string $type, int|string $key, int|string|array|float $preview): void
    {
        if (is_array($preview)) {
            if (empty($preview)) {
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

    function addFormSubmit(int|string|null $key, int|string|array|float $preview): void
    {
        echo "<input type='submit'" . ($key == null ? "" : " name='$key' ") . "value='$preview' style='margin: 0; padding: 0;'><br>";
    }

    function createForm(string $method, bool $space, ?string $url = null): void
    {
        echo ($space ? "<p>" : "") . "<form method='$method'" . ($url == null ? "" : " action='$url' ") . "style='margin: 0; padding: 0;'>";
    }

    function endForm(): void
    {
        echo "</form>";
    }

    // Separator

    $session_account_id = get_communication_key("session_account_id");

    if (empty($session_account_id)) {
        return;
    }
    $account = new Account();
    $staffAccount = $account->getNew($session_account_id);

    if (!$staffAccount->exists()) {
        return;
    }
    $licenseID = get_form_get("id");
    //$licenseID = explode(".", get_form_get("id"));
    //$licenseID = preg_replace("/[^0-9]/", "", $licenseID[sizeof($licenseID) > 1 ? 1 : 0]);
    $platformID = get_form_get("platform");
    $dataRequest = get_form_get("type") == "data";
    $informationLimiter = $dataRequest ? 0 : 15;
    $lengthLimiter = $dataRequest ? 0 : 100;
    $databaseSchemas = get_sql_database_schemas();

    //

    $isNumeric = is_numeric($licenseID);
    $isEmail = is_email($licenseID);
    $staffAccountObj = $staffAccount->getObject();
    $gameCloudUser = new GameCloudUser(
        is_numeric($platformID) ? $platformID : null,
        !$isNumeric ? 0 : $licenseID
    );
    $hasGameCloudUser = $gameCloudUser->isValid();
    $account = $hasGameCloudUser ?
        $gameCloudUser->getAccount()->getAccount(false) :
        $account->getNew(
            $isEmail ? null : ($isNumeric ? $licenseID : null),
            $isEmail ? $licenseID : null, null,
            !$isEmail && !$isNumeric ? $licenseID : null,
            false
        );
    $hasAccount = $account->exists();

    //

    $valid_products = $account->getProduct()->find(null, false);
    $valid_products = $valid_products->getObject();
    $valid_product_names = array();
    $valid_product_tiers = array();

    if (!empty($valid_products)) {
        foreach ($valid_products as $arrayKey => $validProductObject) {
            $validProductObject->name = strip_tags($validProductObject->name);
            $valid_products[$arrayKey] = $validProductObject;
            $valid_product_names[$arrayKey] = $validProductObject->name;

            if (!empty($validProductObject->tiers->all)) {
                foreach ($validProductObject->tiers->all as $tier) {
                    $valid_product_tiers[$tier->id] = $validProductObject->name . ": " . $tier->name;
                }
            }
        }
    }

    //

    $userObject = new stdClass();
    unset($staffAccountObj->password);
    $userObject->staff = $staffAccountObj;
    $userObject->account = new stdClass();
    $userObject->game_cloud = new stdClass();
    $userObject->memory = new stdClass();

    if ($hasAccount) {
        $accountObj = $account->getObject();
        unset($accountObj->password);
        $userObject->account = $accountObj;
        $userObject->account->details = new stdClass();
        $userObject->memory->purchases = $account->exists() ? $account->getPurchases()->getCurrent() : null;

        foreach ($databaseSchemas as $schema) {
            $tables = get_sql_database_tables($schema);

            if (!empty($tables)) {
                foreach ($tables as $table) {
                    $table = $schema . "." . $table;
                    $query = get_sql_query(
                        $table,
                        null,
                        array(
                            array("account_id", $account->getDetail("id")),
                        ),
                        array(
                            "DESC",
                            "id"
                        ),
                        $informationLimiter
                    );

                    if (!empty($query) > 0) {
                        foreach ($query as $rowKey => $row) {
                            unset($row->account_id);

                            if ($lengthLimiter > 0) {
                                foreach ($row as $columnKey => $column) {
                                    if ($row->{$columnKey} !== null) {
                                        $row->{$columnKey} = substr($column, 0, $lengthLimiter);
                                    }
                                }
                            }
                            unset($query[$rowKey]);
                            $query[$row->id] = $row;
                            unset($row->id);
                        }
                        $userObject->account->details->{$table} = $query;
                    }
                }
            }
        }
    }
    if ($hasGameCloudUser) {
        $database = "gameCloud";
        $tables = get_sql_database_tables($database);

        if (!empty($tables)) {
            foreach ($tables as $table) {
                $table = $database . "." . $table;
                $array = array(
                    array("platform_id", $platformID)
                );

                switch ($table) {
                    case GameCloudVariables::LICENSE_MANAGEMENT_TABLE:
                        $array[] = array("number", $licenseID);
                        break;
                    default:
                        $array[] = array("license_id", $licenseID);
                        break;
                }
                $query = get_sql_query(
                    $table,
                    null,
                    $array,
                    array(
                        "DESC",
                        "id"
                    ),
                    $informationLimiter
                );

                if (!empty($query) > 0) {
                    foreach ($query as $rowKey => $row) {
                        unset($row->license_id);
                        unset($row->platform_id);

                        if ($lengthLimiter > 0) {
                            foreach ($row as $columnKey => $column) {
                                if ($row->{$columnKey} !== null) {
                                    $row->{$columnKey} = substr($column, 0, $lengthLimiter);
                                }
                            }
                        }
                        unset($query[$rowKey]);
                        $query[$row->id] = $row;
                        unset($row->id);
                    }
                    $userObject->game_cloud->{$table} = $query;
                }
            }
        }
    }

    if ($dataRequest) {
        header('Content-type: Application/JSON');
        unset($userObject->staff);
        echo json_encode($userObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo "<style>
                    body {
                        overflow: auto;
                        font-family: Verdana;
                        background-size: 100%;
                        background-color: #212121;
                        color: #eee;
                        margin: 0px;
                        padding: 0px;
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

        echo get_text_list_from_iterable($userObject, 0, true);
        $deleteAccount = "deleteAccount";
        $punishAccount = "punishAccount";
        $unpunishAccount = "unpunishAccount";
        $blockAccountFunctionality = "blockAccountFunctionality";
        $restoreAccountFunctionality = "restoreAccountFunctionality";

        $addAlternateAccount = "addAlternateAccount";

        $addToManagement = "addToManagement";
        $removeFromManagement = "removeFromManagement";

        $addProduct = "addProduct";
        $removeProduct = "removeProduct";

        $exchangeProduct = "exchangeProduct";

        $addConfigurationChange = "addConfigurationChange";
        $removeConfigurationChange = "removeConfigurationChange";

        $addDisabledDetection = "addDisabledDetection";
        $removeDisabledDetection = "removeDisabledDetection";

        $executeSqlQuery = "executeSqlQuery";

        $suspendPayPalTransactions = "suspendPayPalTransactions";
        $queuePayPalTransaction = "queuePayPalTransaction";
        $failedPayPalTransaction = "failedPayPalTransaction";

        $clearMemory = "clearMemory";

        // Separator

        if (!empty($_POST)) {
            foreach ($_POST as $postArgumentKey => $postArgument) {
                switch ($postArgumentKey) {
                    case $deleteAccount:
                        $accountIDform = get_form_post("account_id");

                        if ($hasAccount
                            && $account->getDetail("id") == $accountIDform
                            && $staffAccount->getDetail("id") !== $accountIDform) {
                            var_dump($account->getActions()->deleteAccount(
                                !empty(get_form_post("permanent"))
                            ));
                        } else if ($hasAccount) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $punishAccount:
                        if ($hasAccount) {
                            $action = get_form_post("action");
                            $reason = get_form_post("reason");
                            $duration = get_form_post("duration");

                            if (!empty($action) && !empty($reason)) {
                                var_dump($staffAccount->getModerations()->executeAction(
                                    $account->getDetail("id"),
                                    $action,
                                    $reason,
                                    !empty($duration) ? $duration : null,
                                ));
                            }
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $blockAccountFunctionality:
                        if ($hasAccount) {
                            $functionality = get_form_post("functionality");
                            $reason = get_form_post("reason");
                            $duration = get_form_post("duration");

                            if (!empty($functionality) && !empty($reason)) {
                                var_dump($staffAccount->getFunctionality()->executeAction(
                                    $account->getDetail("id"),
                                    $functionality,
                                    $reason,
                                    !empty($duration) ? $duration : null,
                                ));
                            }
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $unpunishAccount:
                        if ($hasAccount) {
                            $action = get_form_post("action");
                            $reason = get_form_post("reason");

                            if (!empty($action) && !empty($reason)) {
                                var_dump($staffAccount->getModerations()->cancelAction(
                                    $account->getDetail("id"),
                                    $action,
                                    $reason,
                                ));
                            }
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $restoreAccountFunctionality:
                        if ($hasAccount) {
                            $functionality = get_form_post("functionality");
                            $reason = get_form_post("reason");

                            if (!empty($functionality) && !empty($reason)) {
                                var_dump($staffAccount->getFunctionality()->cancelAction(
                                    $account->getDetail("id"),
                                    $functionality,
                                    $reason,
                                ));
                            }
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $addToManagement:
                        if ($hasGameCloudUser
                            && $staffAccount->getPermissions()->hasPermission("gamecloud.add.management", true, $hasAccount ? $account : null)) {
                            $product = get_form_post("product");
                            $productID = null;

                            if (!empty($product)) {
                                foreach ($valid_products as $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        $productID = $validProductObject->id;
                                        break;
                                    }
                                }
                            }
                            $reason = get_form_post("reason");
                            $expirationDate = get_form_post("expiration_date");
                            var_dump($gameCloudUser->getVerification()->addLicenseManagement(
                                $productID,
                                get_form_post("type"),
                                !empty($reason) ? $reason : null,
                                !empty($expirationDate) ? $expirationDate : null,
                                null
                            ));
                        } else if ($hasGameCloudUser) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $removeFromManagement:
                        if ($hasGameCloudUser
                            && $staffAccount->getPermissions()->hasPermission("gamecloud.remove.management", true, $hasAccount ? $account : null)) {
                            $product = get_form_post("product");
                            $productID = null;

                            if (!empty($product)) {
                                foreach ($valid_products as $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        $productID = $validProductObject->id;
                                        break;
                                    }
                                }
                            }
                            var_dump($gameCloudUser->getVerification()->removeLicenseManagement(
                                $productID,
                                get_form_post("type")
                            ));
                        } else if ($hasGameCloudUser) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $addProduct:
                        if ($hasAccount
                            && $staffAccount->getPermissions()->hasPermission("account.add.product", true, $account)) {
                            $product = get_form_post("product");

                            if (!empty($product)) {
                                $duration = get_form_post("duration");

                                foreach ($valid_products as $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        $creationDate = get_form_post("creation_date");
                                        $tierForm = get_form_post("tier");

                                        if (empty($tierForm)) {
                                            $tier = null;
                                        } else {
                                            $tier = false;

                                            foreach ($valid_product_tiers as $tierKey => $tierValue) {
                                                if ($tierForm == $tierValue) {
                                                    $tier = $tierKey;
                                                    break;
                                                }
                                            }
                                        }

                                        if ($tier !== false) {
                                            $duration = !empty($duration) ? $duration : null;
                                            $additionalProducts = get_form_post("additional_products");

                                            if (!empty($additionalProducts)) {
                                                $additionalProductsArray = explode(",", $additionalProducts);
                                                $additionalProducts = array();

                                                foreach ($additionalProductsArray as $arrayKey => $additionalProduct) {
                                                    $additionalProducts[$additionalProduct] = $duration;
                                                }
                                            } else {
                                                $additionalProducts = null;
                                            }
                                            var_dump($account->getPurchases()->add(
                                                $validProductObject->id,
                                                $tier,
                                                null,
                                                null,
                                                !empty($creationDate) ? $creationDate : null,
                                                $duration,
                                                !empty(get_form_post("email")),
                                                $additionalProducts
                                            ));
                                        } else {
                                            var_dump("Invalid tier");
                                        }
                                        break;
                                    }
                                }
                            }
                        } else if ($hasAccount) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $removeProduct:
                        if ($hasAccount
                            && $staffAccount->getPermissions()->hasPermission("account.remove.product", true, $account)) {
                            $product = get_form_post("product");

                            if (!empty($product)) {
                                foreach ($valid_products as $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        var_dump($account->getPurchases()->remove(
                                            $validProductObject->id
                                        ));
                                        break;
                                    }
                                }
                            }
                        } else if ($hasAccount) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $exchangeProduct:
                        if ($hasAccount
                            && $staffAccount->getPermissions()->hasPermission("account.exchange.product", true, $account)) {
                            $product = get_form_post("product");

                            if (!empty($product)) {
                                $currentProductID = null;

                                foreach ($valid_products as $validProductObject) {
                                    if ($product == $validProductObject->name) {
                                        $currentProductID = $validProductObject->id;
                                        break;
                                    }
                                }

                                if ($currentProductID !== null) {
                                    $replacementProduct = get_form_post("replacement_product");

                                    foreach ($valid_products as $validProductObject) {
                                        if ($replacementProduct == $validProductObject->name) {
                                            var_dump($account->getPurchases()->exchange(
                                                $currentProductID,
                                                null,
                                                $validProductObject->id,
                                                null,
                                                !empty(get_form_post("email")),
                                            ));
                                            break;
                                        }
                                    }
                                }
                            }
                        } else if ($hasAccount) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $addAlternateAccount:
                        if ($hasAccount
                            && $staffAccount->getPermissions()->hasPermission("account.add.alternate.account", true, $account)) {
                            $name = get_form_post("name");

                            if (!empty($name)) {
                                $information = get_form_post("information");

                                if (!empty($information)) {
                                    var_dump($account->getAccounts()->add(
                                        $name,
                                        $information
                                    ));
                                }
                            }
                        } else if ($hasAccount) {
                            var_dump("No permission");
                        } else {
                            var_dump("Not available form");
                        }
                        break;
                    case $queuePayPalTransaction:
                        if ($staffAccount->getPermissions()->hasPermission("transactions.queue.paypal", true)) {
                            $transactionID = get_form_post("id");

                            if (!empty($transactionID)) {
                                var_dump(queue_paypal_transaction($transactionID));
                            }
                        }
                        break;
                    case $failedPayPalTransaction:
                        if ($staffAccount->getPermissions()->hasPermission("transactions.fail.paypal", true)) {
                            $transactionID = get_form_post("id");

                            if (!empty($transactionID)) {
                                var_dump(process_failed_paypal_transaction($transactionID));
                            }
                        } else {
                            var_dump("No permission");
                        }
                        break;
                    case $executeSqlQuery:
                        if ($staffAccount->getPermissions()->hasPermission("sql.execute.query", true)) {
                            $command = get_form_post("command");

                            if (!empty($command)) {
                                var_dump(sql_query($command));
                            }
                        } else {
                            var_dump("No permission");
                        }
                        break;
                    case $clearMemory:
                        if ($staffAccount->getPermissions()->hasPermission("system.clear.memory", true)) {
                            $key = get_form_post("key");
                            clear_memory(
                                empty($key) ? array() : array($key),
                                !empty(get_form_post("abstract")),
                                get_form_post("limit"),
                                null,
                            );
                        } else {
                            var_dump("No permission");
                        }
                        break;
                    case $suspendPayPalTransactions:
                        if ($hasAccount) {
                            if ($staffAccount->getPermissions()->hasPermission("transactions.suspend.paypal", true)) {
                                $transactions = $account->getTransactions()->getSuccessful(PaymentProcessor::PAYPAL);

                                if (empty($transactions)) {
                                    var_dump("No transactions available");
                                } else {
                                    $reason = get_form_post("reason");
                                    $coverFees = !empty(get_form_post("coverFees"));

                                    foreach ($transactions as $transaction) {
                                        var_dump(suspend_paypal_transaction($transaction->id, $reason, $coverFees));
                                    }
                                }
                            }
                        } else {
                            var_dump("Not available form");
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

        createForm("post", true);
        addFormInput("text", "id", "Transaction ID");
        addFormSubmit($queuePayPalTransaction, "Queue PayPal Transaction");
        endForm();

        createForm("post", true);
        addFormInput("text", "reason", "Reason");
        addFormInput("number", "coverFees", "Cover Fees");
        addFormSubmit($suspendPayPalTransactions, "Suspend PayPal Transaction");
        endForm();

        createForm("post", true);
        addFormInput("text", "id", "Transaction ID");
        addFormSubmit($failedPayPalTransaction, "Mark Failed PayPal Transaction");
        endForm();

        createForm("post", true);
        addFormInput("text", "command", "Command");
        addFormSubmit($executeSqlQuery, "Execute SQL Query");
        endForm();

        createForm("post", true);
        addFormInput("text", "key", "Key");
        addFormInput("number", "abstract", "Abstract");
        addFormInput("number", "limit", "Limit");
        addFormSubmit($clearMemory, "Clear Memory");
        endForm();

        if ($hasAccount) {
            createForm("post", true);
            addFormInput("text", "product", $valid_product_names);
            addFormInput("text", "tier", $valid_product_tiers);
            addFormInput("text", "creation_date", "Creation Date");
            addFormInput("text", "duration", "Time Duration");
            addFormInput("number", "email", "Email");
            addFormInput("text", "additional_products", "Additional Products");
            addFormSubmit($addProduct, "Add Product");
            addFormSubmit($removeProduct, "Remove Product");
            endForm();

            createForm("post", true);
            addFormInput("text", "product", $valid_product_names);
            addFormInput("text", "replacement_product", $valid_product_names);
            addFormInput("number", "email", "Email");
            addFormSubmit($exchangeProduct, "Exchange Product");
            endForm();

            createForm("post", true);
            addFormInput("number", "account_id", "Type the account ID to verify");
            addFormInput("number", "permanent", "Permanent");
            addFormSubmit($deleteAccount, "Delete Account");
            endForm();

            createForm("post", true);
            addFormInput("text", "action", $account->getModerations()->getAvailable());
            addFormInput("text", "reason", "Reason");
            addFormInput("text", "duration", "Time Duration");
            addFormSubmit($punishAccount, "Punish Account");
            endForm();

            createForm("post", true);
            addFormInput("text", "action", $account->getModerations()->getAvailable());
            addFormInput("text", "reason", "Reason");
            addFormSubmit($unpunishAccount, "Unpunish Account");
            endForm();

            createForm("post", true);
            addFormInput("text", "functionality", $account->getFunctionality()->getAvailable());
            addFormInput("text", "reason", "Reason");
            addFormInput("text", "duration", "Time Duration");
            addFormSubmit($blockAccountFunctionality, "Block Account Functionality");
            endForm();

            createForm("post", true);
            addFormInput("text", "functionality", $account->getFunctionality()->getAvailable());
            addFormInput("text", "reason", "Reason");
            addFormSubmit($restoreAccountFunctionality, "Restore Account Functionality");
            endForm();

            $acceptedAccounts = get_sql_query(
                AccountVariables::ACCEPTED_ACCOUNTS_TABLE,
                array("name"),
                array(
                    array("application_id", $account->getDetail("application_id")),
                )
            );

            if (!empty($acceptedAccounts)) {
                foreach ($acceptedAccounts as $arrayKey => $acceptedAccount) {
                    $acceptedAccounts[$arrayKey] = $acceptedAccount->name;
                }
                createForm("post", true);
                addFormInput("text", "name", $acceptedAccounts);
                addFormInput("text", "information", "Account Information");
                addFormSubmit($addAlternateAccount, "Add Alternate Account");
                endForm();
            }
        }

        if ($hasGameCloudUser) {
            createForm("post", true);
            addFormInput("text", "type", GameCloudVerification::managed_license_types);
            addFormInput("text", "product", $valid_product_names);
            addFormInput("text", "reason", "Reason");
            addFormInput("text", "expiration_date", "Expiration Date");
            addFormSubmit($addToManagement, "Add To Management");
            addFormSubmit($removeFromManagement, "Remove From Management");
            endForm();

            createForm("post", true);
            addFormInput("number", "everyone", "Everyone");
            addFormInput("text", "product", $valid_product_names);
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
        }

        // Separator
        echo "</body>";
    }
}
