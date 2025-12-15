<?php
// Arguments

require_once '/var/www/.structure/library/base/form.php';
$action = get_form("action", "");

if (true && in_array($action, array("get", "add"))) { // Toggle database insertions
    $version = get_form("version", "");
    $data = get_form("data", "");

    if (empty($data)) {
        return;
    }
    if (empty($version)) {
        $version = null;
    }
    require_once '/var/www/.structure/library/base/requirements/account_systems.php';
    require_once '/var/www/.structure/library/base/form.php';

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
    } else {
        $user_agent = get_user_agent();

        if (empty($user_agent)) {
            return;
        }
        $ipAddressModified = get_client_ip_address();
    }
    $gameCloudUser = new GameCloudUser(null, null);
    $identification = get_form("identification", "");

    // Account Finder
    if (!empty($identification)) {
        $split = explode("|", $identification, 4);
        $size = sizeof($split);

        if ($size == 3) {
            $licenseID = $split[1];
            $fileID = string_to_integer($split[2]);

            if (is_numeric($licenseID)
                && $licenseID > 0
                && $fileID != 0) {
                $gameCloudUser->setLicense($licenseID);
                $platformID = new MinecraftPlatformConverter($split[0]);
                $platformID = $platformID->getConversion();

                if ($platformID === null) {
                    $accessFailure = 948302520;
                } else {
                    $gameCloudUser->setPlatform($platformID);
                    $account = $gameCloudUser->getAccount()->getAccount();
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

    if ($accessFailure !== null) {
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
    $value = properly_sql_encode(get_form("value", ""), true);

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
        } else if ($data == "ownedEditions") {
            $email = trim(urldecode(get_form("email_address", "")));
            $found = array();
            $all = array("Java", "Bedrock");

            if (is_email($email)) {
                if (empty($found)) {
                    foreach ($all as $edition) {
                        $db = $gameCloudUser->getPurchases()->getFromDatabase(
                            $email,
                            $data . $edition
                        );

                        if ($db === true) {
                            $found[] = $edition;
                        }
                    }
                }
                if (sizeof($found) < sizeof($all)) {
                    if ((!in_array($all[0], $found)
                            || !in_array($all[1], $found))
                        && $gameCloudUser->getPurchases()->hasSpartanPayPalTransaction($email)) {
                        if (!in_array($all[0], $found)) {
                            $found[] = $all[0];
                        }
                        if (!in_array($all[1], $found)) {
                            $found[] = $all[1];
                        }
                    }
                }
            }
            if (sizeof($found) < sizeof($all)) {
                $patreonFullName = trim(urldecode(get_form("patreon_full_name", "")));

                if (!empty($patreonFullName)) {
                    $account = new Account();

                    if ($account->getPatreon()->custom(
                        $patreonFullName,
                        array(25600775) // Both
                    )->isPositiveOutcome()) {
                        foreach ($all as $available) {
                            if (!in_array($available, $found)) {
                                $found[] = $available;
                            }
                        }
                    } else if ($account->getPatreon()->custom(
                        $patreonFullName,
                        array(25600830) // Java
                    )->isPositiveOutcome()) {
                        if (!in_array($all[0], $found)) {
                            $found[] = $all[0];
                        }
                    } else if ($account->getPatreon()->custom(
                        $patreonFullName,
                        array(25600831) // Bedrock
                    )->isPositiveOutcome()) {
                        if (!in_array($all[1], $found)) {
                            $found[] = $all[1];
                        }
                    }
                }
            }
            if (sizeof($found) < sizeof($all)) {
                if ($gameCloudUser->getPlatform() === 2) {
                    if (has_builtbybit_resource_ownership(11196, $gameCloudUser->getLicense())
                        || has_builtbybit_resource_ownership(20142, $gameCloudUser->getLicense())) {
                        if (!in_array($all[0], $found)) {
                            $found[] = $all[0];
                        }
                        if (!in_array($all[1], $found)) {
                            $found[] = $all[1];
                        }
                    }
                } else if ($gameCloudUser->getPlatform() === 3) {
                    $buyer = get_polymart_buyer_details(
                        350,
                        $gameCloudUser->getLicense()
                    );

                    if ($buyer !== null) {
                        if (!in_array($all[0], $found)) {
                            $found[] = $all[0];
                        }
                        if (!in_array($all[1], $found)) {
                            $found[] = $all[1];
                        }
                    }
                }
            }

            if (!empty($found)) {
                echo implode($separator, $found);
            }
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
                        $hash = string_to_integer($row->announcement, true);

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
                $verificationResult = $gameCloudUser->getVerification()->isVerified(
                    $fileID,
                    $ipAddressModified
                );

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
                        $response = has_memory_limit(
                            "game-cloud=" . $data . "=" . string_to_integer($url, true),
                            30,
                            "1 minute"
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
                            if (!has_memory_cooldown(GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE, "15 minutes")) {
                                delete_sql_query(
                                    GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE,
                                    array(
                                        array("creation_date", "<", get_past_date("31 days"))
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
        } else if ($data == "advancedDiscordWebhook") {
            if ($gameCloudUser->getPlatform() === 2) {
                if (!has_builtbybit_resource_ownership(64165, $gameCloudUser->getLicense())) {
                    echo "false";
                    return;
                }
            } else if ($gameCloudUser->getPlatform() === 3) {
                $buyer = get_polymart_buyer_details(
                    0,  // todo
                    $gameCloudUser->getLicense()
                );

                if ($buyer === null) {
                    echo "false";
                    return;
                }
            }
            $url = urldecode(get_form("webhook_url", ""));
            $avatarURL = urldecode(get_form("avatar_url", ""));
            $color = urldecode(get_form("color", ""));
            $author = urldecode(get_form("author", ""));
            $authorURL = urldecode(get_form("author_url", ""));
            $authorIconURL = urldecode(get_form("author_icon_url", ""));
            $title = urldecode(get_form("title", ""));
            $titleURL = urldecode(get_form("title_url", ""));
            $description = urldecode(get_form("description", ""));
            $footer = urldecode(get_form("footer", ""));
            $footerIconURL = urldecode(get_form("footer_icon_url", ""));
            $content = urldecode(get_form("content", ""));
            $fields = urldecode(get_form("fields", ""));
            $fields = json_decode($fields, true);

            if (!is_array($fields)) {
                $fields = array();
            }
            if (true
                && $gameCloudUser->getPlatform() !== 2
                && $gameCloudUser->getPlatform() !== 3
                && has_memory_cooldown(
                    "game-cloud=" . $data . "=" . string_to_integer($url, true),
                    "30 seconds",
                )) {
                echo "The free edition has a 30 seconds cooldown, consider purchasing the premium edition: https://builtbybit.com/resources/64165";
                return;
            }
            $response = send_discord_webhook(
                $url,
                $avatarURL,
                $color,
                $author,
                $authorURL,
                $authorIconURL,
                $title,
                $titleURL,
                $description,
                $footer,
                $footerIconURL,
                $fields,
                $content
            );

            if ($response === true) {
                echo "true";
            } else {
                if (!has_memory_cooldown(GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE, "15 minutes")) {
                    delete_sql_query(
                        GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE,
                        array(
                            array("creation_date", "<", get_past_date("31 days"))
                        )
                    );
                }
                $details = new stdClass();
                $details->fields = $fields;
                $details->description = $description;
                $details->footer = $footer;
                $details->footer_icon_url = $footerIconURL;
                $details->title = $title;
                $details->title_url = $titleURL;
                $details->author = $author;
                $details->author_url = $authorURL;
                $details->author_icon_url = $authorIconURL;
                $details->color = $color;
                $details->avatar_url = $avatarURL;
                $details->webhook_url = $url;
                $details->content = $content;
                sql_insert(
                    GameCloudVariables::FAILED_DISCORD_WEBHOOKS_TABLE,
                    array(
                        "creation_date" => $date,
                        "version" => $version,
                        "platform_id" => $gameCloudUser->getPlatform(),
                        "license_id" => $gameCloudUser->getLicense(),
                        "webhook_url" => $url,
                        "details" => @json_encode(
                            $details,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                        "error" => $response
                    )
                );
                echo $response;
            }
        }
    }
}