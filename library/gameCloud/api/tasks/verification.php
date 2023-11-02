<?php
// Cooldown

require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/memory/init.php';
$ipAddress = get_client_ip_address();
$cacheKey = array($ipAddress, "game-cloud-verification");

if (has_memory_limit($cacheKey, 200, "10 minutes")) {
    return;
}
require_once '/var/www/.structure/library/base/requirements/account_systems.php';
require_once '/var/www/.structure/library/base/form.php';

// Arguments Validation
$licenseID = get_form_get("id");
$hasLicense = is_numeric($licenseID) && $licenseID > 0;
$licenseID = $hasLicense ? $licenseID : null;
$fileID = get_form_get("nonce");
$port = get_form_get("port");
$hasFile = is_numeric($fileID) && $fileID != 0;

// Custom Arguments (Optional)
$adminUser = is_private_connection();

if ($adminUser) {
    $user_agent = properly_sql_encode(get_form_get("user_agent"), true);

    if (strlen($user_agent) == 0) {
        echo "false";
        return;
    }
    $ipAddress = properly_sql_encode(get_form_get("ip_address"), true);

    if (!is_ip_address($ipAddress)) {
        echo "false";
        return;
    }
} else {
    $user_agent = get_user_agent();
}

// Arguments Check
$accessFailure = null;
$productID = null;
$platform = null;
$platformArgument = get_form_get("platform");
$hasPlatformArgument = !empty($platformArgument);
$gameCloudUser = new GameCloudUser(null, $licenseID);
$user_agent_explode = explode(" ", $user_agent, 2);

if (!$hasLicense) {
    $accessFailure = 128940523;
} else if (!$hasFile) {
    $accessFailure = 908324153;
} else if (empty($user_agent)) {
    $accessFailure = 128975028;
} else {
    $application = new Application(null);
    $user_agent_start = $user_agent_explode[0];

    // Product Finder
    if (is_numeric($user_agent_start) && $user_agent_start > 0) {
        $validProductObject = $application->getAccount(0)->getProduct()->find($user_agent_start, false);

        if ($validProductObject->isPositiveOutcome()) {
            $productID = $user_agent_start;
        }
    }

    // Token Finder
    if ($productID === null) {
        $download = $application->getAccount(0)->getDownloads()->find($user_agent_start);

        if ($download->isPositiveOutcome()) {
            $download = $download->getObject();
            $account = $download->account;

            if ($account->exists()) {
                $downloadProductID = $download->product_id;
                $validProductObject = $account->getProduct()->find($downloadProductID, false);

                if ($validProductObject->isPositiveOutcome()) {
                    $validProductObject = $validProductObject->getObject()[0];
                    $acceptedPlatforms = get_accepted_platforms(array("id", "accepted_account_id"));

                    if (!empty($acceptedPlatforms)) {
                        foreach ($acceptedPlatforms as $row) {
                            $acceptedAccount = $account->getAccounts()->hasAdded($row->accepted_account_id, $licenseID, 1);

                            if ($acceptedAccount->isPositiveOutcome()) {
                                $productID = $downloadProductID;
                                $platform = $row->id;
                                $gameCloudUser->setPlatform($platform);
                                break;
                            }
                        }
                    }
                }
            }
        }
    } else if ($hasPlatformArgument) {
        $platform = new MinecraftPlatformConverter($platformArgument);
        $platform = $platform->getConversion();
        $gameCloudUser->setPlatform($platform);
    } else {
        $platform = $gameCloudUser->getInformation()->guessPlatform($ipAddress); // Automatically sets the platform
    }

    if ($accessFailure === null) {
        if ($productID === null || $platform === null) {
            $accessFailure = 768291733;
        } else { // User Verification
            $verificationResult = $gameCloudUser->getVerification()->isVerified($fileID, $productID, $ipAddress);

            if ($verificationResult <= 0) {
                $accessFailure = $verificationResult;
            }
        }
    }
}

if (true && !$adminUser) { // Toggle database insertions
    $date = get_current_date();
    $port = is_port($port) ? $port : null;
    $fileID = $hasFile ? $fileID : null;
    $query = get_sql_query(
        $verifications_table,
        array("id"),
        array(
            array("ip_address", $ipAddress),
            array("port", $port),
            array("platform_id", $platform),
            array("product_id", $productID),
            array("license_id", $licenseID),
            array("file_id", $fileID),
            array("access_failure", $accessFailure),
            array("dismiss", null)
        )
    );

    if (empty($query)) {
        sql_insert(
            $verifications_table,
            array(
                "ip_address" => $ipAddress,
                "port" => $port,
                "creation_date" => $date,
                "last_access_date" => $date,
                "platform_id" => $platform,
                "product_id" => $productID,
                "license_id" => $licenseID,
                "file_id" => $fileID,
                "access_failure" => $accessFailure
            )
        );
    } else {
        foreach ($query as $row) {
            set_sql_query(
                $verifications_table,
                array(
                    "last_access_date" => $date,
                ),
                array(
                    array("id", $row->id)
                )
            );
        }
    }
    if ($hasLicense && sizeof($user_agent_explode) > 1) {
        $staffData = new StaffData($user_agent_explode[1]);

        if ($staffData->found()) {
            $hasAccessFailure = $accessFailure !== null;

            foreach ($staffData->getArray() as $uuid => $info) {
                $name = $info[0];
                $ipAddress = $info[1];
                $hasName = !empty($name);
                $hasIpAddress = !empty($ipAddress);
                $query = get_sql_query(
                    $staff_players_table,
                    array("id"),
                    array(
                        array("uuid", $uuid),
                        array("platform_id", $platform),
                        array("license_id", $licenseID),
                        array("access_failure", $accessFailure),
                        array("ip_address", $hasIpAddress ? $ipAddress : null),
                        array("name", $hasName ? $name : null)
                    ),
                    null,
                    1
                );

                if (!empty($query)) {
                    set_sql_query(
                        $staff_players_table,
                        array(
                            "last_access_date" => $date,
                        ),
                        array(
                            array("id", $query[0]->id)
                        )
                    );
                } else {
                    sql_insert(
                        $staff_players_table,
                        array("platform_id" => $platform,
                            "license_id" => $licenseID,
                            "uuid" => $uuid,
                            "name" => ($hasName ? $name : null),
                            "ip_address" => ($hasIpAddress ? $ipAddress : null),
                            "creation_date" => $date,
                            "last_access_date" => $date,
                            "access_failure" => $accessFailure
                        )
                    );
                }
            }
        }
    }
}

if ($accessFailure !== null && $accessFailure <= 0) {
    echo "false" . ($adminUser ? " (" . $accessFailure . ")" : "");
} else if ($hasPlatformArgument) {
    echo "true"; // Do not return platform when using the argument to avoid local storage inconsistencies
} else {
    echo $platform;
}
