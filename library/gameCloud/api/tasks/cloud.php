<?php
// Arguments

require_once '/var/www/.structure/library/base/form.php';
$version = get_form("version");
$action = get_form("action");

if (true
    && in_array($action, array("get", "add"))
    && is_numeric($version) && $version > 0) { // Toggle database insertions
    $data = get_form("data");

    if (empty($data)) {
        return;
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
    $identification = get_form("identification");

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
                        $account = $gameCloudUser->getInformation()->getAccount();
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
                $downloadProductID = $validProductObject->id;

                if (($allProductsAreAllowed || in_array($downloadProductID, $purposeAllowedProducts))
                    && array_key_exists($version, $validProductObject->supported_versions)) {
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
                $downloadProductID = $download->product_id;
                $validProductObject = $account->getProduct()->find($downloadProductID, false);

                if ($validProductObject->isPositiveOutcome()
                    && ($allProductsAreAllowed || in_array($downloadProductID, $purposeAllowedProducts))) {
                    $validProductObject = $validProductObject->getObject()[0];

                    if (array_key_exists($version, $validProductObject->supported_versions)) {
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

    if ($productObject === null) {
        return;
    }

    if ($accessFailure !== null) {
        $hasAccessFailure = true;
        return;
    } else if ($requiresVerification) {
        if ($gameCloudUser->isValid()) {
            $verificationResult = $gameCloudUser->getVerification()->isVerified($fileID, $productObject->id, $ipAddressModified);

            if ($verificationResult <= 0) {
                $accessFailure = $verificationResult;
                $hasAccessFailure = true;
                return;
            } else {
                $hasAccessFailure = false;
            }
        } else {
            $accessFailure = 689340526;
        }
    } else {
        $hasAccessFailure = false;
    }

    // Connection Counter
    if (!$adminUser) {
        $verificationRequirement = $requiresVerification ? 1 : null;
        $cause = $action . "-" . $data;
        $remainingDaySeconds = strtotime("tomorrow") - time();
        $search_hash = array_to_integer(array(
            $gameCloudUser->getLicense(),
            $ipAddressModified,
            $cause,
            $version,
            $productObject->id,
            $gameCloudUser->getPlatform(),
            $hasAccessFailure ? $accessFailure : null
        ));
        $query = get_sql_query(
            $connection_count_table,
            array("id", "count"),
            array(
                array("search_hash", $search_hash),
            )
        );

        if (!empty($query)) {
            $row = $query[0];
            $count = $row->count;

            if ($count > $remainingDaySeconds) { // One request every second allowance
                return; // Temporarily deny user when threshold is reached
            }
            set_sql_query(
                $connection_count_table,
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
        } else {
            $remainingDaySeconds = strtotime("tomorrow") - time();
            $modifiedDate = date("Y-m-d") . " 00:00:00";

            if ($remainingDaySeconds <= 1000 && !has_memory_cooldown($connection_count_table, "15 minutes")) {
                delete_sql_query(
                    $connection_count_table,
                    array(
                        array("date", "<", $modifiedDate)
                    )
                );
            }
            sql_insert(
                $connection_count_table,
                array(
                    "search_hash" => $search_hash,
                    "verification_requirement" => $verificationRequirement,
                    "platform_id" => $gameCloudUser->getPlatform(),
                    "license_id" => $gameCloudUser->getLicense(),
                    "ip_address" => $ipAddressModified,
                    "token" => $token,
                    "version" => $version,
                    "product_id" => $productObject->id,
                    "cause" => $cause,
                    "count" => 1,
                    "date" => $modifiedDate,
                    "access_failure" => $accessFailure
                ));
        }
    }
    if ($hasAccessFailure) {
        return;
    }
    $value = properly_sql_encode(get_form("value"), true);

    // Processing
    if ($action == "get") {
        if ($data == "disabledDetections") {
            $hasValue = is_numeric($value);
            $query = get_sql_query(
                $disabled_detections_table,
                array("detections"),
                array(
                    array("deletion_date", null),
                    $hasValue ? array("server_version", ">=", $value) : array("server_version", null),
                    null,
                    array("plugin_version", "IS", null, 0),
                    array("plugin_version", $version),
                    null,
                    null,
                    array("platform_id", "IS", null, 0),
                    array("platform_id", $gameCloudUser->getPlatform()),
                    null,
                    null,
                    array("license_id", "IS", null, 0),
                    array("license_id", $gameCloudUser->getLicense()),
                    null,
                ),
                null,
                25
            );
            $result = "";

            if (!empty($query)) {
                $array = array();

                foreach ($query as $row) {
                    $blocks = explode(" ", $row->detections);

                    foreach ($blocks as $block) {
                        $detections = explode("|", $block);

                        if (sizeof($detections) >= 2) {
                            $check = $detections[0];
                            unset($detections[0]);

                            if (array_key_exists($check, $array)) {
                                $storedDetections = $array[$check];

                                foreach ($detections as $detection) {
                                    if (!in_array($detection, $storedDetections)) {
                                        $array[$check][] = $detection;
                                    }
                                }
                            } else {
                                $array[$check] = $detections;
                            }
                        }
                    }
                }
                $result = implode(
                    $line,
                    array_map(
                        function ($key, $value) {
                            return $key . "|" . implode("|", $value);
                        },
                        array_keys($array),
                        $array
                    )
                );
                echo $result;
            }
        } else if ($data == "userIdentification") {
            $query = get_sql_query(
                $connection_count_table,
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
        } else if ($data == "automaticConfigurationChanges") {
            $query = get_sql_query(
                $configuration_changes_table,
                array("id", "file_name", "abstract_option", "value", "if_value"),
                array(
                    array("deletion_date", null),
                    array("product_id", $productObject->id),
                    null,
                    array("version", "IS", null, 0),
                    array("version", $version),
                    null,
                    null,
                    array("license_id", "IS", null, 0),
                    array("platform_id", $gameCloudUser->getPlatform()),
                    array("license_id", $gameCloudUser->getLicense()),
                    null,
                ),
                array(
                    "DESC",
                    "id"
                ),
                100
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $ifValue = $row->if_value;
                    echo $row->file_name . "|" . $row->abstract_option . ":" . $row->value . ($ifValue !== null ? ("|" . $ifValue) : "") . $line;
                }
            }
        } else if ($data == "ownsProduct") {
            $value = is_numeric($value) ? $value : $productObject->id;
            $searchedAndFound = false;

            if ($value > 0 && $gameCloudUser->getInformation()->ownsProduct($value)) {
                echo "true";
            } else {
                echo "false";
            }
        } else if ($data == "staffAnnouncements") {
            try {
                $query = get_sql_query(
                    $staff_announcements_table,
                    array("id", "announcement", "cooldown"),
                    array(
                        array("deletion_date", null),
                        null,
                        array("minimum_version", "IS", null, 0),
                        array("minimum_version", "<=", $version),
                        null,
                        null,
                        array("maximum_version", "IS", null, 0),
                        array("maximum_version", ">=", $version),
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
                    )
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
        } else if ($data == "hasAccount") {
            if ($account->exists()) {
                echo "true";
            } else {
                echo "false";
            }
        }
    } else if ($action == "add") {
        if ($data == "userVerification") {
            if ($gameCloudUser->isValid()) {
                $verificationResult = $gameCloudUser->getVerification()->isVerified($fileID, $productObject->id, $ipAddressModified);

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
                case 1:
                    echo "false"; // Spartan AntiCheat: Old
                    break;
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
                            array("name" => "Player",
                                "value" => "``$player``",
                                "inline" => true),
                            array("name" => "UUID",
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
                                "Spartan AntiCheat",
                                null,
                                get_minecraft_head_image($uuid, 64),
                                $title,
                                null,
                                null,
                                null,
                                "https://vagdedes.com/.images/spartan/logo.png",
                                $details
                            );

                        if ($response === true) {
                            echo "true";
                        } else {
                            global $failed_discord_webhooks_table;

                            if (!has_memory_cooldown($failed_discord_webhooks_table, "15 minutes")) {
                                delete_sql_query(
                                    $failed_discord_webhooks_table,
                                    array(
                                        array("date", "<", get_past_date("31 days"))
                                    )
                                );
                            }
                            sql_insert($failed_discord_webhooks_table,
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
                case 3:
                    echo "false"; // Anti Alt Account & File GUI
                    break;
                case 4:
                    echo "false"; // Ultimate Stats
                    break;
                default:
                    break;
            }
        }
    }
}