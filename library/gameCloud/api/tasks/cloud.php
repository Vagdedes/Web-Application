<?php
// Arguments

require_once '/var/www/.structure/library/base/form.php';
$action = get_form("action", false);

if (true && in_array($action, array("get", "add"))) { // Toggle database insertions
    $version = get_form("version", false);
    $data = get_form("data", false);

    if (empty($data)) {
        return;
    }
    if (empty($version)) {
        $version = null;
    }
    require_once '/var/www/.structure/library/base/requirements/account_systems.php';
    require_once '/var/www/.structure/library/base/form.php';

    $productObject = null;
    $accessFailure = null;
    $fileID = null;
    $token = null;
    $data = properly_sql_encode($data, true);
    $date = get_current_date();
    $line = "\r\n";
    $separator = ">@#&!%<;="; // Old: §@#±&%
    $purpose = new GameCloudConnection($data);
    $purpose = $purpose->getProperties();
    $account = new Account();

    if (!is_object($purpose)
        || $purpose->type !== null && $purpose->type != $action) {
        return;
    }

    // Admin Or User
    $data = properly_sql_encode($data, true);
    $adminUser = is_private_connection();
    $data = $purpose->name;
    $requiresVerification = $purpose->requires_verification !== null;

    if ($adminUser) {
        $ipAddressModified = properly_sql_encode(get_form_get("ip_address"), true);

        if (!is_ip_address($ipAddressModified)) {
            return;
        }
        $user_agent = properly_sql_encode(get_form_get("user_agent"), true);

        if (empty($user_agent)) {
            return;
        }
        $isTokenSearch = !is_numeric($user_agent);
    } else {
        $user_agent = get_user_agent();

        if (empty($user_agent)) {
            return;
        }
        $ipAddressModified = get_client_ip_address();
        $isTokenSearch = !is_numeric($user_agent);
    }
    $purposeAllowedProducts = $purpose->allowed_products;
    $allProductsAreAllowed = $purposeAllowedProducts === null;

    $gameCloudUser = new GameCloudUser(null, null);
    $identification = get_form("identification", false);

    // Account Finder
    if (!empty($identification)) {
        $split = explode("|", $identification, 4);
        $size = sizeof($split);

        if ($size == 3) {
            $licenseID = $split[1];
            $fileID = $split[2];

            if (is_numeric($licenseID) && $licenseID > 0
                && is_numeric($fileID) && $fileID != 0) {
                $gameCloudUser->setLicense($licenseID);
                $platformID = new MinecraftPlatformConverter($split[0]);
                $platformID = $platformID->getConversion();

                if ($platformID === null) {
                    $accessFailure = 948302520;
                } else {
                    $gameCloudUser->setPlatform($platformID);

                    if (!$isTokenSearch) {
                        $account = $gameCloudUser->getAccount()->getAccount();
                    }
                }
            } else if ($requiresVerification) {
                $accessFailure = 899453502;
                $fileID = null;
            }
        } else if ($requiresVerification) {
            $accessFailure = 346980835;
        }
    } else if ($requiresVerification) {
        $accessFailure = 659076543;
    }

    // Product Finder

    if (!$isTokenSearch) {
        if (is_numeric($user_agent) && $user_agent > 0) {
            $validProductObject = $account->getProduct()->find($user_agent, false, false);

            if ($validProductObject->isPositiveOutcome()) {
                $validProductObject = $validProductObject->getObject()[0];

                if (($allProductsAreAllowed
                        || in_array($validProductObject->id, $purposeAllowedProducts))
                    && ($purpose->ignore_version !== null
                        || array_key_exists($version, $validProductObject->supported_versions))) {
                    $productObject = $validProductObject;
                }
            }
        }
    } else { // Token Finder
        $download = $account->getDownloads()->find($user_agent, false);

        if ($download->isPositiveOutcome()) {
            $download = $download->getObject();
            $account = $download->account;

            if ($account->exists()) {
                $validProductObject = $account->getProduct()->find($download->product_id, false);

                if ($validProductObject->isPositiveOutcome()
                    && ($allProductsAreAllowed || in_array($download->product_id, $purposeAllowedProducts))) {
                    $validProductObject = $validProductObject->getObject()[0];

                    if ($purpose->ignore_version !== null
                        || array_key_exists($version, $validProductObject->supported_versions)) {
                        $acceptedPlatforms = get_accepted_platforms(array("id", "accepted_account_id"));

                        if (!empty($acceptedPlatforms)) {
                            foreach ($acceptedPlatforms as $row) {
                                $acceptedAccount = $account->getAccounts()->hasAdded($row->accepted_account_id, $gameCloudUser->getLicense(), 1);

                                if ($acceptedAccount->isPositiveOutcome()) {
                                    $token = $user_agent;
                                    $productObject = $validProductObject;
                                    $gameCloudUser->setPlatform($row->id);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($productObject === null
        || $accessFailure !== null) {
        return;
    }

    // Connection Counter
    if (!$adminUser) {
        $cause = $action . "-" . $data;
        $pastDate = get_past_date("1 day");
        $query = get_sql_query(
            GameCloudVariables::CONNECTION_COUNT_TABLE,
            array("id", "count"),
            array(
                array("license_id", $gameCloudUser->getLicense()),
                array("product_id", $productObject->id),
                array("platform_id", $gameCloudUser->getPlatform()),
                array("ip_address", $ipAddressModified),
                array("cause", $cause),
                array("version", $version),
                array("access_failure", $accessFailure),
                array("date", ">=", $pastDate)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (!empty($query)) {
            $row = $query[0];
            $count = $row->count;
            $remainingDaySeconds = strtotime("tomorrow") - time();

            if ($count > $remainingDaySeconds) { // One request every second allowance
                return; // Temporarily deny user when threshold is reached
            }
            set_sql_query(
                GameCloudVariables::CONNECTION_COUNT_TABLE,
                array(
                    "count" => ($count + 1),
                    "token" => $token
                ),
                array(
                    array("id", $row->id)
                ),
                null,
                1
            );
            if (!has_memory_cooldown(GameCloudVariables::CONNECTION_COUNT_TABLE, "15 minutes")) {
                delete_sql_query(
                    GameCloudVariables::CONNECTION_COUNT_TABLE,
                    array(
                        array("date", "<", get_past_date("31 days"))
                    )
                );
            }
        } else {
            sql_insert(
                GameCloudVariables::CONNECTION_COUNT_TABLE,
                array(
                    "verification_requirement" => $requiresVerification,
                    "platform_id" => $gameCloudUser->getPlatform(),
                    "license_id" => $gameCloudUser->getLicense(),
                    "ip_address" => $ipAddressModified,
                    "token" => $token,
                    "version" => $version,
                    "product_id" => $productObject->id,
                    "cause" => $cause,
                    "count" => 1,
                    "date" => $date,
                    "access_failure" => $accessFailure
                )
            );
        }
    }
    if ($accessFailure !== null) {
        return;
    }
    $value = properly_sql_encode(get_form("value", false), true);

    // Processing
    if ($action == "get") {
        if ($data == "userIdentification") {
            $query = get_sql_query(
                GameCloudVariables::CONNECTION_COUNT_TABLE,
                array("license_id"),
                array(
                    array("license_id", "IS NOT", null),
                    array("access_failure", null),
                    array("ip_address", $ipAddressModified)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($query)) {
                echo $query[0]->license_id;
            }
        } else if ($data == "ownsChecks") {
            $type = strtolower(get_form("type", false));

            switch ($type) {
                case "combat":
                case "movement":
                case "world":
                    $type = strtoupper($type[0]) . substr($type, 1);
                    break;
                default:
                    echo "false";
                    return;
            }
            $paypalEmail = trim(urldecode(get_form("paypal_email", false)));

            if (is_email($paypalEmail)) {
                $inceptionDate = "2025-04-05 00:00:00";
                $legacyExpirationDate = "2025-10-05 00:00:00";
                $legacyExtendedExpirationDate = "2026-01-05 00:00:00";
                $checks = array();

                if ($date <= $legacyExpirationDate) {
                    if ($gameCloudUser->getAccount()->ownsProduct(1)
                        || $gameCloudUser->getAccount()->ownsProduct(16)
                        || $gameCloudUser->getPurchases()->hasVacanPayPalTransaction($paypalEmail)
                        || $gameCloudUser->getPurchases()->hasVacanExtendedPayPalTransaction($paypalEmail)) {
                        $checks[] = "Java";
                        $checks[] = "Bedrock";
                    }
                } else if ($date <= $legacyExtendedExpirationDate
                    && ($gameCloudUser->getAccount()->ownsProduct(26)
                        || $gameCloudUser->getPurchases()->hasVacanExtendedPayPalTransaction($paypalEmail))) {
                    $checks[] = "Java";
                    $checks[] = "Bedrock";
                }

                if (empty($checks)) {
                    foreach (array("Java", "Bedrock") as $edition) {
                        $db = $gameCloudUser->getPurchases()->getFromDatabase(
                            $paypalEmail,
                            $data . $edition . $type
                        );

                        if ($db !== null) {
                            if ($db) {
                                $checks[] = $edition;
                            } else {
                                continue;
                            }
                        }
                        if (!empty($gameCloudUser->getPurchases()->hasPayPalTransaction(
                                $paypalEmail,
                                9.99,
                                1,
                                "Vacan " . $edition . " " . $type . " Checks",
                                $inceptionDate
                            )) || !empty($gameCloudUser->getPurchases()->hasPayPalTransaction(
                                $paypalEmail,
                                29.97,
                                1,
                                "Vacan All " . $edition . " Checks",
                                $inceptionDate
                            ))) {
                            $checks[] = $edition;
                        } else {
                            $transactions = $gameCloudUser->getPurchases()->hasPayPalTransaction(
                                $paypalEmail,
                                0.01,
                                1,
                                array(
                                    "Vacan " . $edition . " " . $type . " Checks",
                                    "Vacan All " . $edition . " Checks"
                                ),
                                $inceptionDate
                            );

                            if (!empty($transactions)) {
                                $transaction = $transactions[0];

                                if (isset($transaction->TIMESTAMP)) {
                                    $timestampDate = reformat_date($transaction->TIMESTAMP);
                                    $timePassed = strtotime($date) - strtotime($timestampDate);

                                    // less than a day
                                    if ($timePassed <= 86_400) {
                                        $checks[] = $edition;
                                    }
                                }
                            }
                        }
                    }
                }

                if (!empty($checks)) {
                    echo implode($separator, $checks);
                }
            }
        } else if ($data == "ownsVacanOne") {
            $paypalEmail = trim(urldecode(get_form("paypal_email", false)));

            if ($gameCloudUser->getAccount()->ownsProduct(26)) {
                echo "true";
                return;
            }
            if (is_email($paypalEmail)) {
                $db = $gameCloudUser->getPurchases()->getFromDatabase(
                    $paypalEmail,
                    $data
                );

                if ($db !== null) {
                    echo($db ? "true" : "false");
                    return;
                }
                if ($gameCloudUser->getPurchases()->hasVacanExtendedPayPalTransaction($paypalEmail)) {
                    echo "true";
                    return;
                }
            }
            $patreonFullName = trim(urldecode(get_form("patreon_full_name", false)));

            if (strlen($patreonFullName) >= 2) {
                $patreonFullName = trim($patreonFullName);
                $patreonSubscriptions = get_patreon2_subscriptions(null, null, null);

                if (!empty($patreonSubscriptions)) {
                    foreach ($patreonSubscriptions as $subscription) {
                        if (trim($subscription?->attributes?->full_name) == $patreonFullName) {
                            if ($subscription?->attributes?->lifetime_support_cents >= 900) {
                                echo "true";
                                return;
                            }
                            break;
                        }
                    }
                }
            }
            echo "false";
        } else if ($data == "staffAnnouncements") {
            try {
                $query = get_sql_query(
                    GameCloudVariables::STAFF_ANNOUNCEMENTS_TABLE,
                    array("id", "announcement", "cooldown"),
                    array(
                        array("deletion_date", null),
                        null,
                        array("version", "IS", null, 0),
                        array("version", $version),
                        null,
                        null,
                        array("platform_id", "IS", null, 0),
                        array("platform_id", $gameCloudUser->getPlatform()),
                        null,
                        null,
                        array("license_id", "IS", null, 0),
                        array("license_id", $gameCloudUser->getLicense()),
                        null,
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", $date),
                        null,
                    ),
                    array(
                        "DESC",
                        "priority"
                    ),
                    5
                );

                if (!empty($query)) {
                    $added = array();

                    foreach ($query as $arrayKey => $row) {
                        $hash = string_to_integer($row->announcement);

                        if (in_array($hash, $added)) {
                            continue;
                        } else {
                            $added[] = $hash;
                        }
                        $query[$arrayKey] = base64_encode(
                            $row->id
                            . $separator . $row->announcement
                            . $separator . $row->cooldown
                        );
                    }
                    echo implode($separator, $query);
                }
            } catch (Throwable $ignored) {
            }
        }
    } else if ($action == "add") {
        if ($data == "userVerification") {
            if ($gameCloudUser->isValid()) {
                $verificationResult = $gameCloudUser->getVerification()->isVerified($fileID, $productObject->id);

                if ($verificationResult <= 0) {
                    echo "false";
                } else if ($gameCloudUser->getPlatform() !== null) {
                    echo $gameCloudUser->getPlatform();
                } else {
                    echo "true";
                }
            } else {
                echo "false";
            }
        } else if ($data == "discordWebhooks") {
            $split = explode($separator, $value, 13);
            $url = null;

            switch ($split[0]) { // Webhook version
                case 2:
                    $url = $split[1] ?? null;
                    $color = $split[2] ?? null;
                    $server = $split[3] ?? null;
                    $player = $split[4] ?? null;
                    $uuid = $split[5] ?? null;
                    $coordinateX = $split[6] ?? null;
                    $coordinateY = $split[7] ?? null;
                    $coordinateZ = $split[8] ?? null;
                    $type = $split[9] ?? null;
                    $information = str_replace($line, " | ", $split[10] ?? null);
                    $title = (!empty($server) && $server != "NULL" ? " (" . $server . ")" : "");

                    if (!empty($player)
                        && is_uuid($uuid)
                        && is_numeric($coordinateX)
                        && is_numeric($coordinateY)
                        && is_numeric($coordinateZ)
                        && !empty($information)) {
                        $details = array(
                            array("name" => $player,
                                "value" => "``$uuid``",
                                "inline" => true),
                            array("name" => "X, Y, Z",
                                "value" => "``$coordinateX``**,** ``$coordinateY``**,** ``$coordinateZ``",
                                "inline" => true),
                            array("name" => $type,
                                "value" => "``$information``",
                                "inline" => false)
                        );
                        $response = has_memory_cooldown(
                            "game-cloud=discord-webhook="
                            . $gameCloudUser->getPlatform()
                            . "-"
                            . $gameCloudUser->getLicense(),
                            "2 seconds"
                        ) ? true
                            : send_discord_webhook(
                                $url,
                                null,
                                $color,
                                "Vacan AntiCheat",
                                null,
                                get_minecraft_head_image($uuid, 64),
                                $title,
                                null,
                                null,
                                null,
                                "https://vagdedes.com/.images/vacan/logo.png",
                                $details
                            );

                        if ($response === true) {
                            echo "true";
                        } else {
                            if (!has_memory_cooldown(GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE, "15 minutes")) {
                                delete_sql_query(
                                    GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE,
                                    array(
                                        array("date", "<", get_past_date("31 days"))
                                    )
                                );
                            }
                            sql_insert(
                                GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE,
                                array(
                                    "creation_date" => $date,
                                    "version" => $version,
                                    "platform_id" => $gameCloudUser->getPlatform(),
                                    "license_id" => $gameCloudUser->getLicense(),
                                    "product_id" => $productObject->id,
                                    "webhook_url" => $url,
                                    "details" => json_encode($details),
                                    "error" => $response
                                )
                            );
                            echo "false";
                        }
                    } else {
                        echo "false";
                    }
                    break;
                default:
                    echo "false";
                    break;
            }
        }
    }
}