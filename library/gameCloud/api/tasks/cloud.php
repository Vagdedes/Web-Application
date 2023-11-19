<?php
// Arguments

require_once '/var/www/.structure/library/base/form.php';
$version = get_form("version");
$action = get_form("action");

if (true
    && in_array($action, array("get", "add"))
    && is_numeric($version) && $version > 0) { // Toggle database insertions
    // Product
    $productID = null;
    $productObject = null;
    $productLatestVersion = 0;

    // User
    $accessFailure = null;
    $licenseID = null;
    $fileID = null;
    $platformID = null;
    $token = null;

    // Purpose
    $data = get_form("data");

    if (empty($data)) {
        return;
    }
    require_once '/var/www/.structure/library/base/requirements/account_systems.php';
    require_once '/var/www/.structure/library/base/form.php';

    $data = properly_sql_encode($data, true);
    $date = get_current_date();
    $line = "\r\n";
    $exception = "exception";
    $separator = ">@#&!%<;="; // Old: §@#±&%
    $user_agent = get_user_agent();
    $purpose = new GameCloudConnection($data);
    $purpose = $purpose->getProperties();
    $account = new Account();

    if (!is_object($purpose)) {
        if ($data === false) {
            echo $exception;
        }
        return;
    }

    // Admin Or User
    $data = properly_sql_encode($data, true);
    $adminUser = is_private_connection();
    $data = $purpose->name;
    $requiresVerification = $purpose->requires_verification !== null;
    $disabledDetections = $data == "disabledDetections";

    if ($adminUser) {
        $ipAddressModified = properly_sql_encode(get_form_get("ip_address"), true);

        if (!is_ip_address($ipAddressModified)) {
            echo "false";
            return;
        }
        $user_agent = properly_sql_encode(get_form_get("user_agent"), true);

        if (empty($user_agent)) {
            return;
        }
        $isTokenSearch = !is_numeric($user_agent);
    } else {
        $ipAddressModified = get_client_ip_address();

        if ($purpose->testing_only !== null) {
            echo $exception;
        }
        if (empty($user_agent)) {
            return;
        }
        $isTokenSearch = !is_numeric($user_agent);
    }
    $purposeAllowedProducts = $purpose->allowed_products;
    $allProductsAreAllowed = $purposeAllowedProducts === null;

    $gameCloudUser = new GameCloudUser(null, null);
    $identification = get_form("identification");

    // Account Finder
    if (!empty($identification)) {
        $split = explode("|", $identification, 2);
        $size = sizeof($split);

        if ($size == 2) {
            $licenseID = $split[0];
            $fileID = $split[1];

            if (is_numeric($licenseID) && $licenseID > 0
                && is_numeric($fileID) && $fileID != 0) {
                $gameCloudUser->setLicense($licenseID);

                if (!$isTokenSearch) {
                    $platformID = $gameCloudUser->getInformation()->guessPlatform($ipAddressModified);

                    if ($platformID === null) {
                        $accessFailure = 948302520;
                    }
                }
            } else if ($requiresVerification) {
                $accessFailure = 899453502;
                $licenseID = null;
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
            $validProductObject = $account->getProduct()->find($user_agent, false);

            if ($validProductObject->isPositiveOutcome()) {
                $validProductObject = $validProductObject->getObject()[0];
                $downloadProductID = $validProductObject->id;

                if (($allProductsAreAllowed || in_array($downloadProductID, $purposeAllowedProducts))
                    && in_array($version, $validProductObject->supported_versions)) {
                    $productLatestVersion = $validProductObject->latest_version;
                    $productID = $downloadProductID;
                    $productObject = $validProductObject;
                }
            }
        }
    } else { // Token Finder
        $download = $account->getDownloads()->find($user_agent);

        if ($download->isPositiveOutcome()) {
            $download = $download->getObject();
            $account = $download->account;

            if ($account->exists()) {
                $downloadProductID = $download->product_id;
                $validProductObject = $account->getProduct()->find($downloadProductID, false);

                if ($validProductObject->isPositiveOutcome()
                    && ($allProductsAreAllowed || in_array($downloadProductID, $purposeAllowedProducts))) {
                    $validProductObject = $validProductObject->getObject()[0];

                    if (in_array($version, $validProductObject->supported_versions)) {
                        $acceptedPlatforms = get_accepted_platforms(array("id", "accepted_account_id"));

                        if (!empty($acceptedPlatforms)) {
                            foreach ($acceptedPlatforms as $row) {
                                $acceptedAccount = $account->getAccounts()->hasAdded($row->accepted_account_id, $licenseID, 1);

                                if ($acceptedAccount->isPositiveOutcome()) {
                                    $productLatestVersion = $validProductObject->latest_version;
                                    $token = $user_agent;
                                    $productID = $downloadProductID;
                                    $productObject = $validProductObject;
                                    $platform = $row->id;
                                    $gameCloudUser->setPlatform($platform);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($productID === null) {
        return;
    }

    if ($accessFailure !== null) {
        $hasAccessFailure = true;

        if ($disabledDetections) {
            echo implode("|__" . $line, $spartan_anticheat_check_names) . "|__";
        } else {
            echo $exception;
        }
        return;
    } else if ($requiresVerification) {
        $verificationResult = $gameCloudUser->getVerification()->isVerified($fileID, $productID, $ipAddressModified);

        if ($verificationResult <= 0) {
            $accessFailure = $verificationResult;
            $hasAccessFailure = true;

            if ($disabledDetections) {
                echo implode("|__" . $line, $spartan_anticheat_check_names) . "|__";
            } else {
                echo $exception;
            }
            return;
        } else {
            $hasAccessFailure = false;
        }
    } else {
        $hasAccessFailure = false;
    }

    // Connection Counter
    if (!$adminUser) {
        $verificationRequirement = $requiresVerification ? 1 : null;
        $cause = $action . "-" . $data;
        $remainingDaySeconds = strtotime("tomorrow") - time();
        $query = get_sql_query(
            $connection_count_table,
            array("id", "count"),
            array(
                array("license_id", $licenseID),
                array("ip_address", $ipAddressModified),
                array("cause", $cause),
                array("version", $version),
                array("product_id", $productID),
                array("platform_id", $platformID),
                array("access_failure", $hasAccessFailure ? $accessFailure : null)
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
                    "verification_requirement" => $verificationRequirement,
                    "platform_id" => $platformID,
                    "license_id" => $licenseID,
                    "ip_address" => $ipAddressModified,
                    "token" => $token,
                    "version" => $version,
                    "product_id" => $productID,
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
        if ($data == "serverLimitations") {
            $returnResult = false;

            if (is_port($value)) {
                echo "1"; // Partly disabled functionality due to platform rules and spam it created
            } else {
                echo "0";
            }
        } else if ($disabledDetections) {
            $hasValue = is_numeric($value);
            $cacheKey = array(
                $hasValue ? $value : null,
                $version,
                $platformID,
                $licenseID,
                $data
            );
            $cache = get_key_value_pair($cacheKey);

            if ($cache !== null) {
                echo $cache;
            } else {
                $query = get_sql_query(
                    $disabled_detections_table,
                    array("detections"),
                    array(
                        $hasValue ? array("server_version", ">=", $value) : array("server_version", null),
                        null,
                        array("plugin_version", "IS", null, 0),
                        array("plugin_version", $version),
                        null,
                        null,
                        array("platform_id", "IS", null, 0),
                        array("platform_id", $platformID),
                        null,
                        null,
                        array("license_id", "IS", null, 0),
                        array("license_id", $licenseID),
                        null,
                    ),
                    null,
                    25
                );
                $result = "";

                if (!empty($query)) {
                    $array = array();
                    $account = $gameCloudUser->getInformation()->getAccount();

                    // Spartan 2.0
                    if (!$account->exists()
                        || !$account->getPurchases()->owns(AccountPatreon::SPARTAN_2_0_JAVA)->isPositiveOutcome()
                        && !$account->getPurchases()->owns(AccountPatreon::SPARTAN_2_0_BEDROCK)->isPositiveOutcome()) {
                        foreach ($spartan_anticheat_1_0_checks as $check => $detections) {
                            $array[$check] = array();

                            foreach ($detections as $detection) {
                                $array[$check][] = " " . $detection;
                            }
                        }
                    }
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
                set_key_value_pair($cacheKey, $result, "1 minute");
            }
        } else if ($data == "crossServerInformation") {
            $split = explode($separator, $value, 4);

            if (sizeof($split) == 3) {
                $port = $split[0];
                $type = $split[1];
                $server_name = $split[2];

                if ($type != "notification") {
                    $server_name = cut_string_at_first_number($server_name);
                }

                $cacheKey = array(
                    $platformID,
                    $licenseID,
                    $productID,
                    $type,
                    $server_name,
                    $data
                );
                $cache = get_key_value_pair($cacheKey);

                if (is_string($cache)) {
                    echo $cache;
                } else if (is_port($port) && !empty($server_name)) {
                    switch ($type) {
                        case "notification":
                            $query = get_sql_query(
                                $cross_server_information_table,
                                array("id", "ip_addresses", "server_name", "information"),
                                array(
                                    array("license_id", $licenseID),
                                    array("file_id", $fileID),
                                    array("version", $version),
                                    array("platform_id", $platformID),
                                    array("product_id", $productID),
                                    array("type", $type),
                                    array("expiration_date", ">", $date)
                                )
                            );

                            if (!empty($query)) {
                                $reply = "";
                                $ip_and_port = $ipAddressModified . ":" . $port;

                                foreach ($query as $row) {
                                    $ipAddresses = $row->ip_addresses;

                                    if (strpos($ipAddresses, "[$ip_and_port]") === false) {
                                        $reply .= base64_encode($row->server_name . $separator . base64_decode($row->information)) . $line;
                                        set_sql_query(
                                            $cross_server_information_table,
                                            array("ip_addresses" => $ipAddresses . "[$ip_and_port]"),
                                            array(
                                                array("id", $row->id)
                                            ),
                                            null,
                                            1
                                        );
                                    }
                                }
                                if (!has_memory_cooldown($cross_server_information_table, "15 minutes")) {
                                    delete_sql_query(
                                        $cross_server_information_table,
                                        array(
                                            array("expiration_date", "<", $date)
                                        )
                                    );
                                }
                                $reply = substr($reply, 0, -strlen($line));
                                set_key_value_pair($cacheKey, $reply, "2 seconds");
                                echo $reply;
                            }
                            break;
                        case "log":
                        case "statistic":
                        case "exemption":
                        case "modification":
                        case "configuration":
                            $query = get_sql_query(
                                $cross_server_information_table,
                                array("id", "ip_addresses", "information"),
                                array(
                                    array("license_id", $licenseID),
                                    array("file_id", $fileID),
                                    array("version", $version),
                                    array("platform_id", $platformID),
                                    array("product_id", $productID),
                                    array("type", $type),
                                    array("expiration_date", ">", $date),
                                    array("server_name", $server_name)
                                )
                            );

                            if (!empty($query)) {
                                $reply = "";
                                $ip_and_port = $ipAddressModified . ":" . $port;

                                foreach ($query as $row) {
                                    $ipAddresses = $row->ip_addresses;

                                    if (strpos($ipAddresses, "[$ip_and_port]") === false) {
                                        $reply .= $row->information . $line;
                                        set_sql_query(
                                            $cross_server_information_table,
                                            array("ip_addresses" => $ipAddresses . "[$ip_and_port]"),
                                            array(
                                                array("id", $row->id)
                                            ),
                                            null,
                                            1
                                        );
                                    }
                                }
                                if (!has_memory_cooldown($cross_server_information_table, "15 minutes")) {
                                    delete_sql_query(
                                        $cross_server_information_table,
                                        array(
                                            array("expiration_date", "<", $date)
                                        )
                                    );
                                }
                                $reply = substr($reply, 0, -strlen($line));
                                set_key_value_pair($cacheKey, $reply, "10 seconds");
                                echo $reply;
                            }
                            break;
                        default:
                            $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-type");
                            break;
                    }
                } else {
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-value");
                }
            } else {
                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
            }
        } else if ($data == "outdatedVersionCheck") {
            $outdatedVersionCheck_refreshRate = array(1, "hour");
            $result = false;

            if (is_numeric($value) && $value > 0) {
                $artificialVersion = $version + $value;

                if ($artificialVersion < $productLatestVersion) {
                    $result = true;
                }
            } else {
                $result = $version < $productLatestVersion;
            }

            if ($result) {
                if ($token !== null) {
                    echo "You are using an outdated version. "
                        . "Consider becoming a Patron to receive updates earlier: https://www.patreon.com/Vagdedes";
                } else {
                    echo "You are using an outdated version. "
                        . "Join the Discord server to learn about the auto-updater feature.";
                }
            } else {
                echo "false";
            }
        } else if ($data == "userIdentification") {
            set_sql_cache("1 minute");
            $query = get_sql_query(
                $verifications_table,
                array("license_id"),
                array(
                    array("access_failure", null),
                    array("dismiss", null),
                    array("ip_address", $ipAddressModified)
                ),
                array(
                    "DESC",
                    "last_access_date"
                ),
                1
            );

            if (!empty($query)) {
                echo $query[0]->license_id;
            } else {
                echo $exception; // Purposely returns exception if no user is found to prevent unauthorised use of the plugin
            }
        } else if ($data == "automaticConfigurationChanges") {
            if (is_port($value)) {
                $cacheKey = array(
                    $platformID,
                    $licenseID,
                    $productID,
                    $version,
                    $ipAddressModified,
                    $data
                );
                $cache = get_key_value_pair($cacheKey);

                if (!has_memory_cooldown($cacheKey, "10 seconds")) {
                    $query = get_sql_query(
                        $configuration_changes_table,
                        array("id", "file_name", "abstract_option", "completed_ip_addresses", "value", "if_value"),
                        array(
                            array("product_id", $productID),
                            null,
                            array("version", "IS", null, 0),
                            array("version", $version),
                            null,
                            null,
                            array("license_id", "IS", null, 0),
                            array("platform_id", $platformID),
                            array("license_id", $licenseID),
                            null,
                        ),
                        null,
                        50
                    );

                    if (!empty($query)) {
                        foreach ($query as $row) {
                            $ipAddresses = $row->completed_ip_addresses;
                            $isNull = $ipAddresses === null;
                            $ip_and_port = "[" . $ipAddressModified . ":" . $value . "]";

                            if ($isNull || strpos($ipAddresses, $ip_and_port) === false) {
                                $ifValue = $row->if_value;
                                echo $row->file_name . "|" . $row->abstract_option . ":" . $row->value . ($ifValue !== null ? ("|" . $ifValue) : "") . $line;

                                if ($isNull) {
                                    $ipAddresses = $ip_and_port;
                                } else {
                                    $ipAddresses .= $ip_and_port;
                                }
                                set_sql_query(
                                    $configuration_changes_table,
                                    array("completed_ip_addresses" => $ipAddresses),
                                    array(
                                        array("id", $row->id)
                                    ),
                                    null,
                                    1
                                );
                            }
                        }
                    }
                }
            } else {
                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-port");
            }
        } else if ($data == "punishedPlayers") {
            if ($gameCloudUser->getInformation()->getConnectionCount($productID, $ipAddressModified, $version)
                >= 1) { // Server Limits
                $split = explode($separator, $value, 3);

                if (sizeof($split) == 2) {
                    $uuid = $split[0];

                    if (is_uuid($uuid)) {
                        $playerIpAddress = $split[1];
                        $noPlayerIpAddress = $playerIpAddress == "NULL";
                        $playerIpAddress = $noPlayerIpAddress ? null : $playerIpAddress;

                        $cacheKey = array(
                            $uuid,
                            $playerIpAddress,
                            $data
                        );
                        $cache = get_key_value_pair($cacheKey);

                        if (is_string($cache)) {
                            echo $cache;
                        } else if ($noPlayerIpAddress || is_ip_address($playerIpAddress)) {
                            $result = "false";

                            if ($noPlayerIpAddress) {
                                $query = get_sql_query(
                                    $punished_players_table,
                                    array("id", "player_ip_address"),
                                    array(
                                        array("creation_date", ">", get_past_date("1 year")),
                                        array("uuid", $uuid),
                                    ),
                                    array(
                                        "DESC",
                                        "id"
                                    )
                                );
                            } else {
                                $query = get_sql_query(
                                    $punished_players_table,
                                    array("id", "player_ip_address"),
                                    array(
                                        array("creation_date", ">", get_past_date("1 year")),
                                        null,
                                        array("uuid", "=", $uuid, 0),
                                        array("player_ip_address", $playerIpAddress),
                                        null,
                                    ),
                                    array(
                                        "DESC",
                                        "id"
                                    )
                                );
                            }

                            if (!empty($query)) {
                                $points = 0;

                                foreach ($query as $row) {
                                    if (!$noPlayerIpAddress) {
                                        $rowPlayerIpAddress = $row->player_ip_address;

                                        if ($playerIpAddress != $rowPlayerIpAddress) {
                                            set_sql_query(
                                                $punished_players_table,
                                                array(
                                                    "last_access_date" => $date,
                                                    "player_ip_address" => $playerIpAddress
                                                ),
                                                array(
                                                    array("id", $row->id)
                                                ),
                                                null,
                                                1,
                                            );
                                        } else {
                                            set_sql_query(
                                                $punished_players_table,
                                                array("last_access_date" => $date),
                                                array(
                                                    array("id", $row->id)
                                                ),
                                                null,
                                                1
                                            );
                                        }
                                    } else {
                                        set_sql_query(
                                            $punished_players_table,
                                            array("last_access_date" => $date),
                                            array(
                                                array("id", $row->id)
                                            ),
                                            null,
                                            1
                                        );
                                    }
                                    $points += 1;

                                    if ($points == 2) {
                                        $result = "true";
                                    }
                                }
                            }
                            set_key_value_pair($cacheKey, $result, "10 seconds");
                            echo $result;
                        } else {
                            $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-ipAddress");
                        }
                    } else {
                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-uuid");
                    }
                } else {
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                }
            }
        } else if ($data == "ownsProduct") {
            $value = is_numeric($value) ? $value : $productID;
            $searchedAndFound = false;

            if ($value > 0 && $gameCloudUser->getInformation()->ownsProduct($value)) {
                echo "true";
            } else {
                echo "false";
            }
        } else if ($data == "customerSupportCommands") {
            if (!$productObject->is_free) {
                if (is_port($value)) {
                    $cacheKey = array(
                        $platformID,
                        $licenseID,
                        $productID,
                        $version,
                        $data
                    );

                    if (!has_memory_cooldown($cacheKey, "10 seconds")) {
                        $query = get_sql_query(
                            $customer_support_commands_table,
                            array("id", "user", "functionality", "ip_addresses"),
                            array(
                                array("platform_id", $platformID),
                                array("license_id", $licenseID),
                                array("product_id", $productID),
                                array("expiration_date", ">=", $date),
                                null,
                                array("version", "=", $version, 0),
                                array("version", null),
                                null,
                            )
                        );

                        if (!empty($query)) {
                            $ip_and_port = $ipAddressModified . ":" . $value;
                            $array = array();

                            foreach ($query as $row) {
                                $ipAddresses = $row->ip_addresses;

                                if (strpos($ipAddresses, "[$ip_and_port]") === false) {
                                    $ipAddresses .= "[$ip_and_port]";
                                    set_sql_query(
                                        $customer_support_commands_table,
                                        array("ip_addresses" => $ipAddresses),
                                        array(
                                            array("id", $row->id)
                                        ),
                                        null,
                                        1
                                    );
                                    $user = $row->user;
                                    $functionality = $row->functionality;
                                    $array[] = base64_encode($user === null ? "NULL" : $user) . $separator . base64_encode($functionality === null ? "NULL" : $functionality);
                                }
                            }

                            if (!empty($array)) {
                                echo implode($line, $array);
                            }
                        }
                    }
                } else {
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-value");
                }
            } else {
                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data);
            }
        } else if ($data == "staffAnnouncements") {
            try {
                set_sql_cache("1 minute");
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
                        array("platform_id", $platformID),
                        null,
                        null,
                        array("license_id", "IS", null, 0),
                        array("license_id", $licenseID),
                        null,
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null,
                    ),
                    array(
                        "DESC",
                        "priority"
                    )
                );

                if (!empty($query)) {
                    foreach ($query as $arrayKey => $row) {
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
        if ($data == "serverSpecifications") {
            $split = explode($separator, $value, 7);

            if (sizeof($split) == 6) {
                $serverVersion = $split[0];
                $port = $split[1];
                $cpu = $split[2];
                $ram = $split[3];
                $plugins = $split[4];
                $motd = base64_decode($split[5]);

                if (strlen($serverVersion) >= 3 && strlen($serverVersion) <= 7
                    && is_port($port)
                    && is_numeric($cpu)
                    && is_numeric($ram)
                    && is_numeric($plugins)
                    && strlen($motd) <= 8192
                    && $cpu > 0 && $ram > 0 && $plugins > 0) {
                    if (is_numeric(str_replace(".", "", $serverVersion))) {
                        $serverVersion = "'$serverVersion'";
                    } else {
                        $serverVersion = "NULL";
                    }
                    $query = get_sql_query(
                        $server_specifications_table,
                        array("id"),
                        array(
                            array("port", $port),
                            array("ip_address", $ipAddressModified),
                        )
                    );

                    if (empty($query)) {
                        set_sql_cache("1 minute");
                        $query = get_sql_query(
                            $server_specifications_table,
                            array("id"),
                            array(
                                array("ip_address", $ipAddressModified),
                            )
                        );

                        if (sizeof($query) >= 256) {
                            $query = null;
                        } else {
                            $query = "INSERT INTO $server_specifications_table (product_id, ip_address, version, port, cpu, ram, plugins, motd) VALUES ('$productID', '$ipAddressModified', $serverVersion, '$port', '$cpu', '$ram', '$plugins', '$motd');";
                        }
                    } else {
                        $query = "UPDATE $server_specifications_table SET product_id = '$productID', cpu = '$cpu', ram = '$ram', plugins = '$plugins', version = $serverVersion, motd = '$motd' WHERE id = '" . $query[0]->id . "';";
                    }

                    if ($query !== null && sql_query($query)) {
                        echo "true";
                    } else {
                        echo "false";
                    }
                } else {
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-value");
                }
            } else {
                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
            }
        } else if ($data == "crossServerInformation") {
            $limit = 600;
            $split = explode($separator, $value, $limit + 3 + 1); // limit + arguments + extra

            if (sizeof($split) >= 4) {
                $port = $split[0];
                $type = $split[1];
                $server_name = $split[2];

                if (is_port($port) && !empty($type) && !empty($server_name)) {
                    if ($type != "notification") {
                        $server_name = cut_string_at_first_number($server_name);
                    }
                    switch ($type) {
                        case "notification":
                        case "log":
                        case "statistic":
                        case "exemption":
                        case "modification":
                        case "configuration":
                            $counter = 0;
                            unset($split[0]);
                            unset($split[1]);
                            unset($split[2]);
                            $ip_and_port = $ipAddressModified . ":" . $port;
                            $future_date = get_future_date("2 minutes"); // Most requests are made within less than a minute, so keep it low to prevent the memory table from becoming full

                            foreach ($split as $information) {
                                $counter++;
                                $information = str_replace($line, "", $information);
                                sql_insert(
                                    $cross_server_information_table,
                                    array("platform_id" => $platformID,
                                        "product_id" => $productID,
                                        "license_id" => $licenseID,
                                        "file_id" => $fileID,
                                        "version" => $version,
                                        "type" => $type,
                                        "server_name" => $server_name,
                                        "information" => $information,
                                        "creation_date" => $date,
                                        "expiration_date" => $future_date,
                                        "ip_addresses" => "[$ip_and_port]"
                                    ));

                                if ($counter == $limit) {
                                    break;
                                }
                            }
                            if (!has_memory_cooldown($cross_server_information_table, "15 minutes")) {
                                delete_sql_query(
                                    $cross_server_information_table,
                                    array(
                                        array("expiration_date", "<", $date),
                                    )
                                );
                            }
                            echo "true";
                            break;
                        default:
                            $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-type");
                            break;
                    }
                } else {
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-value");
                }
            } else {
                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
            }
        } else if ($data == "discordWebhooks") {
            $maximum = 12;
            $split = explode($separator, $value, $maximum + 1);
            $webhookVersion = $split[0];
            unset($split[0]); // Do not remove as the sizeof below accounts for this removed character

            // Verification
            $splitLength = array(
                1 => 10,
                2 => $maximum - 1,
                3 => 8,
                4 => 8
            );
            $allowedInformation = array(
                /* Spartan */
                1 => array(
                    "Hacker" => "5 minutes",
                    "Suspected" => "5 minutes",
                    "Ban" => "1 second",
                    "Kick" => "1 second",
                    "Warning" => "1 second",
                    "Report" => "1 second",
                    "Staff Chat" => "1 second"
                ),
                2 => array(
                    "Hacker" => "5 minutes",
                    "Suspected" => "5 minutes",
                    "Ban" => "1 second",
                    "Kick" => "1 second",
                    "Warning" => "1 second",
                    "Report" => "1 second",
                    "Staff Chat" => "1 second",
                    "Punishment" => "1 second"
                ),
                3 => array(
                    // AntiAltAccount
                    "Prevention" => "1 second",
                    "Exemption" => "1 second",
                    // FileGUI
                    "Deletion" => "1 second",
                    "Modification" => "1 second",
                    "Creation" => "1 second"
                ),
                4 => $ultimatestats_statistic_names
            );
            $nameLength = 16 + 4;
            $uuidLength = 36;
            $informationLength = 1024;

            // Presets
            $url = null;
            $gameName = "Minecraft";
            $titleName = "Incoming Notification";
            $showEcosystem = false;

            switch ($webhookVersion) {
                case 1:
                    if (sizeof($split) == $splitLength[$webhookVersion]) {
                        $url = $split[1];
                        $color = $split[2];

                        $server = $split[3];
                        $player = $split[4];
                        $uuid = $split[5];
                        $coordinateX = $split[6];
                        $coordinateY = $split[7];
                        $coordinateZ = $split[8];
                        $type = $split[9];
                        $information = str_replace($line, " | ", $split[10]);

                        $timeCooldown = $allowedInformation[$webhookVersion][$type] ?? null;

                        if ($timeCooldown !== null
                            && !empty($player) && strlen($player) <= $nameLength
                            && strlen($uuid) == $uuidLength
                            && is_numeric($coordinateX) && is_numeric($coordinateY) && is_numeric($coordinateZ)
                            && !empty($information) && strlen($information) <= $informationLength) {
                            if (canSend_GameCloud_DiscordWebhook($platformID, $licenseID, $productID,
                                $player . "-" . $uuid . "-" . $type . "-" . $information,
                                $timeCooldown)) {
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
                                $response = send_discord_webhook($url, $color,
                                    $gameName, $server,
                                    null, null, null,
                                    $titleName, null,
                                    $details);

                                if ($response === true) {
                                    echo "true";
                                } else {
                                    submit_GameCloud_FailedDiscordWebhook(
                                        $version,
                                        $platformID,
                                        $licenseID,
                                        $productID,
                                        $url,
                                        $details,
                                        $response
                                    );
                                    $url = null;
                                    echo "false";
                                }
                            } else {
                                echo "false";
                            }
                        } else {
                            echo "false";
                        }
                    } else {
                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                    }
                    break;
                case 2:
                    if (sizeof($split) == $splitLength[$webhookVersion]) {
                        $url = $split[1];
                        $color = $split[2];

                        $server = $split[3];
                        $player = $split[4];
                        $uuid = $split[5];
                        $coordinateX = $split[6];
                        $coordinateY = $split[7];
                        $coordinateZ = $split[8];
                        $type = $split[9];
                        $information = str_replace($line, " | ", $split[10]);
                        $showEcosystem = $split[11] == "true";

                        $timeCooldown = $allowedInformation[$webhookVersion][$type] ?? null;

                        if ($timeCooldown !== null
                            && !empty($player) && strlen($player) <= $nameLength
                            && strlen($uuid) <= $uuidLength
                            && is_numeric($coordinateX) && is_numeric($coordinateY) && is_numeric($coordinateZ)
                            && !empty($information) && strlen($information) <= $informationLength) {
                            if (canSend_GameCloud_DiscordWebhook($platformID, $licenseID, $productID,
                                $player . "-" . $uuid . "-" . $type . "-" . $information,
                                $timeCooldown)) {
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
                                $response = send_discord_webhook($url, $color,
                                    $gameName, $server,
                                    null, null, null,
                                    $titleName, null,
                                    $details);

                                if ($response === true) {
                                    echo "true";
                                } else {
                                    submit_GameCloud_FailedDiscordWebhook(
                                        $version,
                                        $platformID,
                                        $licenseID,
                                        $productID,
                                        $url,
                                        $details,
                                        $response
                                    );
                                    $url = null;
                                    echo "false";
                                }
                            } else {
                                echo "false";
                            }
                        } else {
                            echo "false";
                        }
                    } else {
                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                    }
                    break;
                case 3:
                    if (sizeof($split) == $splitLength[$webhookVersion]) {
                        $url = $split[1];
                        $color = $split[2];

                        $server = $split[3];
                        $player = $split[4];
                        $uuid = $split[5];
                        $action = $split[6];
                        $information = str_replace($line, " | ", $split[7]);
                        $showEcosystem = $split[8] == "true";

                        $timeCooldown = $allowedInformation[$webhookVersion][$action] ?? null;

                        if ($timeCooldown !== null
                            && !empty($player) && strlen($player) <= $nameLength
                            && strlen($uuid) <= $uuidLength
                            && !empty($information) && strlen($information) <= $informationLength) {
                            if (canSend_GameCloud_DiscordWebhook($platformID, $licenseID, $productID,
                                $player . "-" . $uuid . "-" . $action . "-" . $information,
                                $timeCooldown)) {
                                $details = array(
                                    array("name" => "Player",
                                        "value" => "``$player``",
                                        "inline" => true),
                                    array("name" => "UUID",
                                        "value" => "``$uuid``",
                                        "inline" => true),
                                    array("name" => "Action",
                                        "value" => "``$action``",
                                        "inline" => true),
                                    array("name" => "Information",
                                        "value" => "``$information``",
                                        "inline" => false)
                                );
                                $response = send_discord_webhook($url, $color,
                                    $gameName, $server,
                                    null, null, null,
                                    $titleName, null,
                                    $details);

                                if ($response === true) {
                                    echo "true";
                                } else {
                                    submit_GameCloud_FailedDiscordWebhook(
                                        $version,
                                        $platformID,
                                        $licenseID,
                                        $productID,
                                        $url,
                                        $details,
                                        $response
                                    );
                                    $url = null;
                                    echo "false";
                                }
                            } else {
                                echo "false";
                            }
                        } else {
                            echo "false";
                        }
                    } else {
                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                    }
                    break;
                case 4:
                    if (sizeof($split) == $splitLength[$webhookVersion]) {
                        $url = $split[1];
                        $color = $split[2];

                        $server = $split[3];
                        $player = $split[4];
                        $uuid = $split[5];
                        $statistic = $split[6];
                        $information = str_replace($line, " | ", $split[7]);
                        $showEcosystem = $split[8] == "true";

                        if (in_array($statistic, $allowedInformation)
                            && !empty($player) && strlen($player) <= $nameLength
                            && strlen($uuid) <= $uuidLength
                            && !empty($information) && strlen($information) <= $informationLength) {
                            if (canSend_GameCloud_DiscordWebhook($platformID, $licenseID, $productID,
                                $player . "-" . $uuid . "-" . $statistic . "-" . $information,
                                "1 second")) {
                                $details = array(
                                    array("name" => "Player",
                                        "value" => "``$player``",
                                        "inline" => true),
                                    array("name" => "UUID",
                                        "value" => "``$uuid``",
                                        "inline" => true),
                                    array("name" => "Statistic",
                                        "value" => "``$statistic``",
                                        "inline" => true),
                                    array("name" => "Information",
                                        "value" => "``$information``",
                                        "inline" => false)
                                );
                                $response = send_discord_webhook($url, $color,
                                    $gameName, $server,
                                    null, null, null,
                                    $titleName, null,
                                    $details);

                                if ($response === true) {
                                    echo "true";
                                } else {
                                    submit_GameCloud_FailedDiscordWebhook(
                                        $version,
                                        $platformID,
                                        $licenseID,
                                        $productID,
                                        $url,
                                        $details,
                                        $response
                                    );
                                    $url = null;
                                    echo "false";
                                }
                            } else {
                                echo "false";
                            }
                        } else {
                            echo "false";
                        }
                    } else {
                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                    }
                    break;
                default:
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-version");
                    break;
            }

            if ($showEcosystem && $url !== null) {
                $account = $gameCloudUser->getInformation()->getAccount();

                // Spartan 2.0
                if (!$account->exists()
                    || !$account->getPurchases()->owns(AccountPatreon::SPARTAN_2_0_JAVA)->isPositiveOutcome()
                    && !$account->getPurchases()->owns(AccountPatreon::SPARTAN_2_0_BEDROCK)->isPositiveOutcome()) {
                    foreach (array(
                                 $spartan_anticheat_2_0_discord_advertisement,
                                 $discord_bot_discord_advertisement
                             ) as $webhookPlan) {
                        if (send_discord_webhook_by_plan(
                                $webhookPlan,
                                $url
                            ) === 1) {
                            break;
                        }
                    }
                }
            }
        } else if ($data == "customerSupport") {
            if (!$productObject->is_free) {
                $split = explode($separator, $value, 7);

                if (sizeof($split) == 6) {
                    $maximumLimit = 30;
                    $contactPlatform = $split[0];
                    $contactInformation = $split[1];
                    $columnType = $split[2];
                    $columnInformation = $split[3];
                    $userInformation = $split[4];
                    $softwareInformation = $split[5];

                    switch ($contactPlatform) {
                        case "discord":
                        case "email":
                            switch ($columnType) {
                                case "user":
                                case "functionality":
                                    $customerSupportType = GameCloudVerification::managed_license_types[3];
                                    set_sql_cache("1 minute");
                                    $query = get_sql_query(
                                        $license_management_table,
                                        array("reason", "expiration_date"),
                                        array(
                                            array("number", $licenseID),
                                            array("type", $customerSupportType),
                                            array("platform_id", $platformID),
                                            array("product_id", $productID)
                                        )
                                    );

                                    if (!empty($query)) {
                                        $break = false;

                                        foreach ($query as $row) {
                                            $rowExpirationDate = $row->expiration_date;

                                            if ($rowExpirationDate === null || $date <= $rowExpirationDate) {
                                                echo $row->reason;
                                                $break = true;
                                                break;
                                            }
                                        }

                                        if ($break) {
                                            break;
                                        }
                                    }
                                    $pastDate = get_past_date("1 day");
                                    set_sql_cache("1 minute");
                                    $query = get_sql_query(
                                        $customer_support_table,
                                        array("id"),
                                        array(
                                            array("ip_address", $ipAddressModified),
                                            array("creation_date", ">", $pastDate)
                                        ),
                                        null,
                                        $maximumLimit
                                    );

                                    if (sizeof($query) === $maximumLimit) {
                                        echo "Your IP daily limit has been reached.";
                                        break;
                                    }
                                    if ($columnType == "functionality") {
                                        set_sql_cache("1 minute");
                                        $query = get_sql_query(
                                            $customer_support_table,
                                            array("id"),
                                            array(
                                                array("license_id", $licenseID),
                                                array("platform_id", $platformID),
                                                array("product_id", $productID),
                                                array("contact_platform", $contactPlatform),
                                                array("functionality", $columnInformation),
                                                array("creation_date", ">", get_past_date("8 hours"))
                                            ),
                                            null,
                                            1
                                        );

                                        if (!empty($query)) {
                                            echo "Your Functionality daily limit has been reached.";
                                            break;
                                        }
                                    }
                                    set_sql_cache("1 minute");
                                    $query = get_sql_query(
                                        $customer_support_table,
                                        array($columnType),
                                        array(
                                            array("license_id", $licenseID),
                                            array("platform_id", $platformID),
                                            array("product_id", $productID),
                                            array("contact_platform", $contactPlatform),
                                            array("creation_date", ">", $pastDate)
                                        )
                                    );
                                    $rowCount = sizeof($query);

                                    if ($rowCount > 0) {
                                        if ($rowCount === 15) {
                                            echo "Your general " . $contactPlatform . " daily limit has been reached.";
                                            break;
                                        } else {
                                            $break = false;
                                            $rowCount = 0;

                                            foreach ($query as $row) {
                                                if ($row->{$columnType} == $columnInformation) {
                                                    $rowCount += 1;

                                                    if ($rowCount === 3) {
                                                        echo "Your daily " . $contactPlatform . " limit has been reached for this " . $columnType . ".";
                                                        $break = true;
                                                        break;
                                                    }
                                                }
                                            }

                                            if ($break) {
                                                break;
                                            }
                                        }
                                    }

                                    if (sql_insert(
                                        $customer_support_table,
                                        array(
                                            "creation_date" => $date,
                                            "ip_address" => $ipAddressModified,
                                            "platform_id" => $platformID,
                                            "product_id" => $productID,
                                            "license_id" => $licenseID,
                                            "contact_platform" => $contactPlatform,
                                            "contact_information" => $contactInformation,
                                            $columnType => $columnInformation,
                                            "user_information" => $userInformation,
                                            "software_information" => $softwareInformation == "NULL" ? null : $softwareInformation
                                        ))) {
                                        $customerSupport = new CustomerSupport();
                                        $customerSupport->clearCache();
                                        $account = $account->getNew(null, CustomerSupport::EMAIL);

                                        if ($account->exists()
                                            && !has_memory_cooldown(
                                                array(
                                                    CustomerSupport::EMAIL,
                                                    "game-cloud-customer-support-email"
                                                ), "15 minutes")) {
                                            $account->getEmail()->send("customerSupport",
                                                array(
                                                    "platformID" => $platformID,
                                                    "licenseID" => $licenseID,
                                                    "discordTag" => $contactInformation,
                                                    "productName" => $productObject->name,
                                                ), "account", false);
                                        }
                                        send_discord_webhook_by_plan(
                                            "customer-support",
                                            $customer_support_discord_webhook,
                                            array(
                                                "discordUsername" => $contactInformation,
                                                "columnType" => strtoupper($columnType[0]) . substr(strtolower(str_replace("_", "-", $columnType)), 1),
                                                "columnInformation" => $columnInformation,
                                                "explanation" => $userInformation
                                            ),
                                            "1 second"
                                        );
                                        echo "true";
                                    } else {
                                        echo "false";
                                    }
                                    break;
                                default:
                                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-columnType");
                                    break;
                            }
                            break;
                        default:
                            $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-contactPlatform");
                            break;
                    }
                } else {
                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                }
            } else {
                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data);
            }
        } else if ($data == "punishedPlayers") {
            if ($gameCloudUser->getInformation()->getConnectionCount($productID, $ipAddressModified, $version)
                >= 2) { // Server Limits, Server Specifications
                $cacheKey = array(
                    $platformID,
                    $licenseID,
                    $productID,
                    $data
                );

                if (has_memory_cooldown($cacheKey, "10 seconds")) {
                    echo "false";
                } else {
                    $limit = 1000; // 1000 is the max for the SQL insert method
                    $individuals = explode($separator, $value, $limit + 1);
                    $individualsCount = sizeof($individuals);

                    if ($individualsCount > 0) {
                        $punishedPlayers = array();

                        for ($position = 0; $position < (min($limit, $individualsCount) - 1); $position++) {
                            $split = explode($separator, base64_decode($individuals[$position]), 3);

                            if (sizeof($split) == 2) {
                                $uuid = $split[0];

                                if (is_uuid($uuid)) {
                                    $playerIpAddress = $split[1];
                                    $noPlayerIpAddress = $playerIpAddress == "NULL";

                                    if ($noPlayerIpAddress || is_ip_address($playerIpAddress)) {
                                        $punishedPlayer = new stdClass();
                                        $punishedPlayer->uuid = $uuid;
                                        $punishedPlayer->ipAddress = $noPlayerIpAddress ? null : $playerIpAddress;
                                        $punishedPlayers[] = $punishedPlayer;
                                    } else {
                                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-ipAddress");
                                        break;
                                    }
                                } else {
                                    $punishedPlayers = array();
                                    $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-uuid");
                                    break;
                                }
                            } else {
                                $punishedPlayers = array();
                                $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split"); // Attention: added recently
                                break;
                            }
                        }

                        if (!empty($punishedPlayers)) {
                            $success = false;
                            $insertValues = array();
                            $query = get_sql_query(
                                $punished_players_table,
                                array(
                                    "id",
                                    "creation_date",
                                    "uuid",
                                    "player_ip_address"
                                ),
                                array(
                                    array("product_id", $productID),
                                    array("license_id", $licenseID),
                                    array("platform_id", $platformID)
                                ),
                                array(
                                    "DESC",
                                    "id"
                                )
                            );
                            $hasRows = !empty($query);
                            $dateToTime = strtotime($date);

                            foreach ($punishedPlayers as $counter => $punishedPlayer) {
                                $uuid = $punishedPlayer->uuid;
                                $playerIpAddress = $punishedPlayer->ipAddress;
                                $noPlayerIpAddress = $playerIpAddress != null;
                                $continue = true;

                                if ($hasRows) {
                                    foreach ($query as $row) {
                                        $minutes = ($dateToTime - strtotime($row->creation_date)) / 60.0;

                                        if ($minutes >= 0.1) { // 5 seconds cooldown
                                            $sameIpAddress = !$noPlayerIpAddress && $row->player_ip_address == $playerIpAddress;

                                            if ($sameIpAddress || $row->uuid == $uuid) {
                                                $continue = false;
                                                $noPlayerIpAddress |= $sameIpAddress;
                                                $playerIpAddressModification = !$noPlayerIpAddress && !$sameIpAddress;

                                                if ($minutes >= 525600) { // Equal or more than a year
                                                    $array = array(
                                                        "version" => $version,
                                                        "creation_date" => $date,
                                                        "last_access_date" => $date,
                                                    );

                                                    if ($playerIpAddressModification) {
                                                        $array["player_ip_address"] = $playerIpAddress;
                                                    }
                                                    if (set_sql_query(
                                                        $punished_players_table,
                                                        $array,
                                                        array(
                                                            array("id", $row->id)
                                                        ),
                                                        null,
                                                        1
                                                    )) {
                                                        $success = true;
                                                    }
                                                } else {
                                                    $array = array(
                                                        "last_access_date" => $date,
                                                    );

                                                    if ($playerIpAddressModification) {
                                                        $array["player_ip_address"] = $playerIpAddress;
                                                    }
                                                    set_sql_query(
                                                        $punished_players_table,
                                                        $array,
                                                        array(
                                                            array("id", $row->id)
                                                        ),
                                                        null,
                                                        1
                                                    );
                                                }
                                            }
                                        } else {
                                            $continue = false;
                                        }
                                    }
                                }

                                if ($continue) {
                                    if ($noPlayerIpAddress) {
                                        $playerIpAddress = "NULL";
                                    }
                                    $insertValues[] = "('$platformID', '$productID', '$licenseID', '$version', '$date', '$date', '$uuid', $playerIpAddress)";
                                    $success = true;
                                }
                            }

                            if ($success) {
                                if (!empty($insertValues)) {
                                    sql_query("INSERT INTO $punished_players_table (platform_id, product_id, license_id, version, creation_date, last_access_date, uuid, player_ip_address) VALUES " . implode(", ", $insertValues) . ";");
                                }
                                echo "true";
                            } else {
                                echo "false";
                            }
                        }
                    } else {
                        $gameCloudUser->getVerification()->timeoutAccess($version, $productID, $ipAddressModified, $action . "-" . $data . "-split");
                    }
                }
            }
        }
    }
}