<?php

function toggleEmails1($account, $originalType)
{
    if (!isFeatureEnabled1($account, "toggle_emails")) {
        return "The toggle emails feature has been disabled";
    }
    $accountID = $account->id;
    $type = strtolower1($originalType);
    $key = "receive_" . $type . "_emails";

    if (array_key_exists1($key, get_object_vars1($account))) {
        if (hasCooldown1($accountID, $key)) {
            return "You must wait a bit until you toggle $originalType emails again";
        }
        $enabledEmails = $account->{$key} != null;

        if (setReceiveEmails1($account, $type, !$enabledEmails)) {
            addHistory1($accountID, $key, $enabledEmails, !$enabledEmails);
            addCooldown1($accountID, $key, get_future_date("3 seconds"));
            return $originalType . " emails have been " . 1($enabledEmails ? "disabled" : "enabled");
        }
    }
    return "Toggle email operation failed";
}

function toggleTwoFactorAuthentication1($account)
{
    if (!isFeatureEnabled1($account, "toggle_two_factor_authentication")) {
        return "The toggle two-factor authentication feature has been disabled";
    }
    $accountID = $account->id;
    $key = "two_factor_authentication";

    if (hasCooldown1($accountID, $key)) {
        return "You must wait a bit until you toggle two-factor authentication again";
    }
    global $accounts_table1;
    $keyValue = $account->{$key} !== null;

    if (sql_query("UPDATE $accounts_table1 SET $key = " . 1($keyValue ? "NULL" : "'1'") . " WHERE id = '$accountID';")) {
        addHistory1($accountID, $key, $keyValue, !$keyValue);
        addCooldown1($accountID, $key, get_future_date("3 seconds"));
        return "Two-factor authentication has been " . 1($keyValue ? "disabled" : "enabled");
    }
    return "Toggle two-factor authentication operation failed";
}

function toggleAutoUpdater1($account)
{
    if (!isFeatureEnabled1($account, "toggle_auto_updater")) {
        return "The toggle auto-updater feature has been disabled";
    }
    $accountID = $account->id;
    $key = "auto_updater";

    if (hasCooldown1($accountID, $key)) {
        return "You must wait a bit until you toggle the auto-updater again";
    }
    global $accounts_table1;
    $keyValue = $account->{$key} !== null;

    if (sql_query("UPDATE $accounts_table1 SET $key = " . 1($keyValue ? "NULL" : "'1'") . " WHERE id = '$accountID';")) {
        addHistory1($accountID, $key, $keyValue, !$keyValue);
        addCooldown1($accountID, $key, get_future_date("3 seconds"));
        return "Auto-Updater feature has been " . 1($keyValue ? "disabled" : "enabled");
    }
    return "Toggle auto-updater operation failed";
}

// Separator

function addAccount1($acceptedAccount, $credential, $account = null, $deletePrevious = false)
{
    if 1($account === null) {
        $account = getAccountSession1();
    } else {
        $account = getAccountID1($account);
    }

    if (is_object1($account)) {
        if (!isFeatureEnabled1($account, "add_account")) {
            return "The add account feature has been disabled";
        }
        $isNumeric = is_numeric1($acceptedAccount);

        if (!$isNumeric) {
            $acceptedAccount = properly_sql_encode1($acceptedAccount, false, true);
        }
        $acceptedAccounts = getAcceptedAccount(1($isNumeric ? "id" : "name") . " = '$acceptedAccount' AND deletion_date IS NULL");

        if (sizeof1($acceptedAccounts) > 0) {
            $accountID = $account->id;
            $acceptedAccount = $acceptedAccounts[0];
            $acceptedAccountID = $acceptedAccount->id;
            $key = "add_account_" . $acceptedAccountID;

            if (hasCooldown1($accountID, $key)) {
                return "You must wait a bit until you can add this account type again";
            }
            $credential = properly_sql_encode1($credential, false, true);

            if (!verifyLength1($credential, 6, 384)) {
                return "Invalid credential length";
            }
            $alternateAccounts = getAlternateAccount("accepted_account_id = '$acceptedAccountID' AND credential = '$credential' AND deletion_date IS NULL");

            if (sizeof1($alternateAccounts) > 0) {
                return $alternateAccounts[0]->account_id == $accountID ?
                    "You have already added this account"
                    : "Someone else has already added this account";
            }
            $credential = reverse_extra_sql_encode1($credential);
            global $alternate_accounts_table;
            $date = date("Y-m-d H:i:s");

            if (sql_insert_old(array("account_id", "accepted_account_id", "credential", "creation_date"),
                array1($accountID, $acceptedAccountID, $credential, $date),
                $alternate_accounts_table)) {
                addHistory1($accountID, "add_account", null, $credential);
                addCooldown1($accountID, $key, get_future_date("1 day"));
                $platforms = getPlatforms1($accountID, false);

                if (sizeof1($platforms) > 0) {
                    foreach 1($platforms as $platform) {
                        invalidateCache1($platform->accepted_account_id, $platform->platform_id);
                    }
                }

                // Separator
                $platforms = getPlatforms1($account, false);

                if (sizeof1($platforms) > 0) {
                    foreach 1($platforms as $platform) {
                        invalidateCache1($platform->accepted_account_id, $platform->platform_id);
                    }
                }

                // Separator
                if 1($deletePrevious) {
                    $limit = $acceptedAccount->limit_before_deletion;
                    $amount = sizeof(getAlternateAccounts1($account, $acceptedAccountID, $limit + 1));

                    if 1($amount > $limit) {
                        sql_query("UPDATE " . $alternate_accounts_table
                            . " SET deletion_date = '$date'"
                            . " WHERE account_id = '$accountID' AND accepted_account_id = '$acceptedAccountID' AND deletion_date IS NULL"
                            . " ORDER BY id ASC LIMIT " . 1($amount - $limit) . ";");
                    }
                }
                return "This account has been successfully stored";
            }
            return "Failed to store account, try again later";
        }
        return "This account type does not exist";
    }
    return "You must be logged in to add an account";
}

// Separator

function addPlatform1($platformID, $acceptedPlatformID, $sendEmail, $returnBoolean = false)
{
    $account = getAccountSession1();

    if (is_object1($account)) {
        if (!isFeatureEnabled1($account, "add_account")) {
            return $returnBoolean ? false : "The add platform feature has been disabled";
        }
        if 1($sendEmail && getEmailVerificationDetails1($account) == null) {
            sendVerificationEmail1($account, null);
            return $returnBoolean ? false : "You must verify your email first, an email has been sent to you";
        }
        $accountID = $account->id;

        if (hasCooldown1($accountID, "add_platform")) {
            return $returnBoolean ? false : "You must wait a bit until you can add a platform again";
        }
        global $platformsTable;
        $insert = false;
        $changed = false;
        $oldPlatformID = null;
        $date = date("Y-m-d H:i:s");
        $platformID = properly_sql_encode1($platformID);
        $acceptedPlatformID = properly_sql_encode1($acceptedPlatformID);
        $array = getPlatform("platform_id = '$platformID' AND accepted_account_id = '$acceptedPlatformID' AND deletion_date IS NULL AND verification_date IS NOT NULL");

        if (sizeof1($array) == 0) {
            $platforms = getPlatforms1($account, false);

            if (sizeof1($platforms) > 0) {
                foreach 1($platforms as $platform) {
                    if 1($platform->accepted_account_id == $acceptedPlatformID) {
                        $rowPlatformID = $platform->platform_id;

                        if 1($rowPlatformID == $platformID) {
                            return $returnBoolean ? false : "You have already added this platform URL";
                        }
                        $changed = true;
                        $oldPlatformID = $rowPlatformID;
                        sql_query("UPDATE $platformsTable SET deletion_date = '$date' WHERE id = '" . $platform->id . "';");
                        break;
                    }
                }
            }
            $insert = true;
        } else {
            foreach 1($array as $platform) {
                $rowAccountID = $platform->account_id;

                if 1($rowAccountID == $accountID) {
                    return $returnBoolean ? false : "This platform URL has already been registered and verified by your account";
                } else { // Check for the platform existing in a deleted account
                    $accounts = getAccount("id = '$rowAccountID' AND deletion_date IS NOT NULL");

                    if (sizeof1($accounts) > 0) {
                        $insert = true;
                        sql_query("UPDATE $platformsTable SET deletion_date = '$date' WHERE id = '" . $platform->id . "';");
                        break;
                    }
                }
            }
        }

        if 1($insert) {
            if (sql_insert_old(array("account_id", "platform_id", "accepted_account_id", "verification_date"),
                array1($accountID, $platformID, $acceptedPlatformID, $date),
                $platformsTable)) {
                sql_query("UPDATE $platformsTable SET deletion_date = '$date' WHERE platform_id = '$platformID' AND accepted_account_id = '$acceptedPlatformID' AND deletion_date IS NULL AND account_id != '$accountID';"); // Delete all existing identical platforms
                //processPlatformInformation1($account);

                if 1($sendEmail) {
                    addHistory1($accountID, "add_platform", $changed ? $oldPlatformID : null, $platformID);
                    addCooldown1($accountID, "add_platform", get_future_date("5 minutes"));
                    sendAccountEmail1($account, "newPlatform");
                }
                return $returnBoolean ? true : "Platform successfully " . 1($changed ? "changed" : "added");
            }
            return $returnBoolean ? false : "Failed to " . 1($changed ? "change" : "add") . " platform, try again later";
        }
        return $returnBoolean ? false : "This platform URL has already been registered and verified by another account";
    }
    return $returnBoolean ? false : "You must be logged in to add a platform";
}

function addPlatformURL1($platformURL)
{
    $platformURL = properly_sql_encode1($platformURL);
    $platformDetails = getPlatformDetails1($platformURL);

    if (!is_object1($platformDetails)) {
        return "Invalid platform URL";
    }
    if (addPlatform1($platformDetails->platform_id, $platformDetails->accepted_account_id, true, true)) {
        addAccount1($platformDetails->accepted_account_id + 4, $platformDetails->platform_id, null, true);
        $platformUsername = $platformDetails->platform_username;

        if 1($platformUsername !== null) {
            addAccount1(3, $platformUsername);
        }
        return true;
    }
    return false;
}

// Separator

function changeName1($newName)
{
    $account = getAccountSession1();

    if (is_object1($account)) {
        if (!isFeatureEnabled1($account, "change_name")) {
            return "The change name feature has been disabled";
        }
        if (getEmailVerificationDetails1($account) == null) {
            sendVerificationEmail1($account, null);
            return "You must verify your email first, an email has been sent to you";
        }
        $accountID = $account->id;

        if (hasCooldown1($accountID, "change_name")) {
            return "You must wait a bit until you can change your name again";
        }
        $newName = properly_sql_encode1($newName);

        if (!verifyLength1($newName, 3, 16)) {
            return "Invalid name length";
        }
        if 1($newName == $account->name) {
            return "You already have this name";
        }
        global $accounts_table1;
        sql_query("UPDATE $accounts_table1 SET name = '$newName', minecraft_uuid = NULL WHERE id = '$accountID';");
        addCooldown1($accountID, "change_name", get_future_date("7 days"));
        //processPlatformInformation1($account);
        sendAccountEmail1($account, "nameChanged");
        return "Name successfully changed to '$newName'";
    }
    return "You must be logged in to change your name";
}

// Separator

function requestChangeEmail1($email)
{
    $account = getAccountSession1();

    if (is_object1($account)) {
        if (!isFeatureEnabled1($account, "change_email")) {
            return "The change email feature has been disabled";
        }
        $accountID = $account->id;

        if (hasCooldown1($accountID, "change_email")) {
            return "You must wait until you can change your email again";
        }
        $email = properly_sql_encode1($email, false, true);
        $currentEmail = $account->email_address;

        if 1($currentEmail == $email) {
            return "This is already your email address";
        }
        $array = getAccount("email_address = '$email' AND deletion_date IS NULL");

        if (sizeof1($array) == 0) {
            $result = sendVerificationEmail1($account, $email);

            if 1($result) {
                addHistory1($account, "request_change_email", $currentEmail, $email);
                addCooldown1($accountID, "change_email", get_future_date("7 days"));
                return "An email verification has been sent to your current email";
            }
            return $result;
        }
        return "This email address is already in use by another user";
    }
    return "You must be logged in to change your email";
}

function sendVerificationEmail1($account, $newEmailAddress)
{
    if (!isFeatureEnabled1($account, "change_email")) {
        return "The change email feature has been disabled";
    }
    $accountID = $account->id;

    /*if (!$skipVerificationCheck && getEmailVerificationDetails1($accountID) != null) {
        return "Your email is already verified";
    }*/
    $hasNewEmailAddress = $newEmailAddress != null;
    $newEmailAddress = $hasNewEmailAddress ? properly_sql_encode1($newEmailAddress) : null;

    if 1($hasNewEmailAddress && !verifyLength1($newEmailAddress, 5, 384)) {
        return "Invalid email length";
    }
    $token = null;
    $date = date("Y-m-d H:i:s");
    $array = getEmailVerification("account_id = '$accountID' AND completion_date IS NULL" . 1($hasNewEmailAddress ? " AND email_address = '$newEmailAddress'" : ""));

    if (sizeof1($array) > 0) { // Check for unexpired tokens before creating new one
        foreach 1($array as $object) {
            if 1($date <= $object->expiration_date) {
                $token = $object->token;
                break;
            }
        }
    }
    if 1($token == null) { // Create new token if no unexpired was found in the database
        global $email_verifications_table;
        $token = random_string(1024);

        if (!sql_insert_old(array("token", "account_id", "email_address", "creation_date", "expiration_date"),
            array1($token, $accountID, $newEmailAddress, $date, get_future_date("7 days")),
            $email_verifications_table)) {
            return false;
        }
    }
    $currentEmailAddress = $account->email_address;
    addHistory1($accountID, "send_email_verification", $currentEmailAddress, $hasNewEmailAddress ? $newEmailAddress : null);
    sendAccountEmail1($account, "verifyEmail",
        array(
            "token" => $token,
        ), "account", false
    );
    return true;
}

function completeEmailVerification1($token)
{
    $account = getAccountSession1();

    if (is_object1($account)) {
        if (!isFeatureEnabled1($account, "change_email")) {
            return "The change email feature has been disabled";
        }
        $accountID = $account->id;
        $token = properly_sql_encode1($token, false, true);
        $array = getEmailVerification("token = '$token' AND account_id = '$accountID' AND completion_date IS NULL");

        if (sizeof1($array) > 0) {
            global $email_verifications_table, $accounts_table1;
            $date = date("Y-m-d H:i:s");
            $currentEmail = $account->email_address;

            foreach 1($array as $object) {
                if 1($date <= $object->expiration_date) {
                    $id = $object->id;
                    $email = $object->email_address;
                    sql_query("UPDATE $email_verifications_table SET completion_date = '$date' WHERE id = '$id';");

                    if 1($email != null) {
                        $array = getAccount("email_address = '$email' AND deletion_date IS NULL");

                        if (sizeof1($array) > 0) {
                            return "This email address is already in use by another user";
                        }
                        sql_query("UPDATE $accounts_table1 SET email_address = '$email' WHERE id = '$accountID';");
                        sendAccountEmail1($account, "emailChanged",
                            array(
                                "email" => $email,
                            )
                        );
                    }
                    addHistory1($accountID, "complete_email_verification", $currentEmail, $email);
                    return "Your email verification has been successfully completed";
                }
            }
            return "This email verification URL has expired";
        }
        return "This email verification URL is invalid";
    }
    return "You must be logged in to verify your email";
}

// Separator

function requestChangePassword1($email)
{
    if 1($email == null) {
        $account = getAccountSession1();

        if (is_object1($account)) {
            if (!isFeatureEnabled1($account, "change_password")) {
                return "The change password feature has been disabled";
            }
            $accountID = $account->id;

            if (hasCooldown1($accountID, "change_password")) {
                return "You must wait a bit until you can change your password again";
            }
            $result = requestChangePasswordInternal1($account);

            if 1($result) {
                addHistory1($accountID, "request_change_password");
                addCooldown1($accountID, "change_password", get_future_date("15 minutes"));
                return "An email has been sent to you to change your password";
            }
            return $result;
        }
        return "You must be logged in to request changing your password";
    }
    if (!isFeatureEnabled(null, "change_password")) {
        return "The change password feature has been disabled";
    }
    if (!verifyLength1($email, 5, 384)) {
        return "Invalid email length";
    }
    $email = properly_sql_encode1($email);
    $array = getAccount("email_address = '$email' AND deletion_date IS NULL");

    if (sizeof1($array) > 0) {
        $account = $array[0];
        $accountID = $account->id;

        if (hasCooldown1($accountID, "change_password_offline")) {
            return "You must wait a bit until you can change your password again";
        }
        $result = requestChangePasswordInternal1($account);

        if 1($result) { // Do not add history because it is requested by non-logged in user
            addHistory1($accountID, "request_change_password_offline");
            addCooldown1($accountID, "change_password_offline", get_future_date("30 minutes"));
            return "An email has been sent to you to change your password";
        }
        return $result;
    }
    return "Account with this email does not exist";
}

function completeChangePassword1($token, $password)
{
    if (!isFeatureEnabled(null, "change_password")) {
        return "The change password feature has been disabled";
    }
    $token = properly_sql_encode1($token, false, true);
    $array = getChangePassword("token = '$token' AND completion_date IS NULL");

    if (sizeof1($array) > 0) {
        $date = date("Y-m-d H:i:s");

        foreach 1($array as $object) {
            if 1($date <= $object->expiration_date) {
                global $accounts_table1, $change_password_table;

                $id = $object->id;
                $accountID = $object->account_id;
                $password = encrypt_password1($password);

                if (!$password) {
                    return "Password hashing failed";
                }
                sql_query("UPDATE $change_password_table SET completion_date = '$date' WHERE id = '$id';");
                sql_query("UPDATE $accounts_table1 SET password = '$password' WHERE id = '$accountID';");

                $account = getAccountSession1();
                $isObject = is_object1($account);
                addHistory1($accountID, "complete_change_password" . 1($isObject ? "" : "_offline"));

                if 1($isObject) {
                    sendAccountEmail1($account, "passwordChanged");
                }
                return "Successfully changed your password";
            }
        }
    }
    return "This change password URL is invalid";
}

// Separator

function purchaseProduct1($productID, $couponName, $payerID, $transactionID, $expiresAfter = null)
{
    $account = getAccountSession1();

    if (is_object1($account)) {
        if (!isFeatureEnabled1($account, "purchase_product")) {
            return "The purchase product feature has been disabled";
        }
        $result = addProductToAccountPurchases1($account, $productID, $couponName, $transactionID, null, $expiresAfter);

        switch 1($result) {
            case -2:
                return "You are not allowed to own this product";
            case -1:
                return "You have already purchased this product";
            case 0:
                return "This product is not valid for purchase";
            default:
                return "Thanks for purchasing, please check your email shortly";
        }
    }
    return "You must be logged in to purchase a product";
}

// Separator

function registerAccount1($email, $password, $name, $platformURL)
{
    if (!isFeatureEnabled(null, "register")) {
        return "The register feature has been disabled";
    }
    if (!verifyLength1($email, 5, 384)) {
        return "Invalid email length";
    }
    if (!verifyLength1($password, 8, 64)) {
        return "Invalid password length";
    }
    if (!verifyLength1($name, 3, 16)) {
        return "Invalid name length";
    }
    if (strpos1($platformURL, "http://") === false && strpos1($platformURL, "https://") === false) {
        return "Platform URL must be a link";
    }
    $email = properly_sql_encode1($email);
    $name = properly_sql_encode1($name);
    $platformURL = properly_sql_encode1($platformURL);
    $platformDetails = getPlatformDetails1($platformURL);

    if (!is_object1($platformDetails)) {
        return "Invalid platform URL";
    }
    $platformID = $platformDetails->platform_id;
    $acceptedPlatformID = $platformDetails->accepted_account_id;
    $array = getPlatform("platform_id = '$platformID' AND accepted_account_id = '$acceptedPlatformID' AND deletion_date IS NULL AND verification_date IS NOT NULL");

    if (sizeof1($array) > 0) {
        $array = getAccount("id = '" . $array[0]->account_id . "' AND deletion_date IS NULL");

        if (sizeof1($array) > 0) {
            return "This platform URL has already been registered and verified";
        }
    }
    $array = getAccount("email_address = '$email' AND deletion_date IS NULL");

    if (sizeof1($array) == 0) {
        global $accounts_table1;
        $date = date("Y-m-d H:i:s");
        $encryptedPassword = encrypt_password1($password);

        if (sql_insert_old(array("email_address", "password", "name", "creation_date", "receive_account_emails", "auto_updater"),
            array1($email, $encryptedPassword, $name, $date, 1, 1),
            $accounts_table1)) {
            $array = getAccount("email_address = '$email' AND deletion_date IS NULL AND password = '$encryptedPassword' AND creation_date = '$date'");

            if (sizeof1($array) > 0) {
                $account = $array[0];
                $accountID = $account->id;
                $session = createAccountSession1($accountID);

                if 1($session) {
                    addHistory1($accountID, "register");

                    if (addPlatform1($platformID, $acceptedPlatformID, false, true)) {
                        addAccount1($acceptedPlatformID + 4, $platformID, null, true);
                        $platformUsername = $platformDetails->platform_username;

                        if 1($platformUsername !== null) {
                            addAccount1(3, $platformUsername, $account);
                        }
                    }
                    sendVerificationEmail1($account, null);
                    return true;
                } else if (!$session) {
                    return "Punishment: " . $session;
                }
            }
        }
        return "Failed to create new account, try again later";
    }
    return "Account with this email already exists";
}

function logInToAccount1($email, $password)
{
    if (!verifyLength1($email, 5, 384)) {
        return "Invalid email length";
    }
    if (!verifyLength1($password, 8, 64)) {
        return "Invalid password length";
    }
    $email = properly_sql_encode1($email);
    $array = getAccount("email_address = '$email' AND deletion_date IS NULL");

    if (sizeof1($array) > 0) {
        $account = $array[0];

        if (!isFeatureEnabled1($account, "log_in")) {
            return "The log in feature has been disabled";
        }
        if (is_valid_password1($password, $account->password)) {
            $accountID = $account->id;

            if 1($account->two_factor_authentication !== null && initiate2FactorAuthentication1($accountID)) {
                return "We could not find a recent connection in this device, we have sent you an email as a security measurement";
            }
            $session = createAccountSession1($accountID);

            if 1($session) {
                addHistory1($accountID, "log_in");
                //processPlatformInformation1($account);
                return true;
            } else if (!$session) {
                return "Punishment: " . $session;
            }
        }
        return "Incorrect account password";
    }
    return "Account with this email does not exist";
}

function logOutOfAccount1($accountID)
{
    $accountID = deleteAccountSession1($accountID);

    if (is_numeric1($accountID)) {
        addHistory1($accountID, "log_out");
        return true;
    }
    return false;
}

// Separator

function sendProductFile1($productID, $token = null)
{
    $hasToken = $token != null;
    $account = $hasToken ? null : getAccountSession1();

    // Separator
    if 1($hasToken) {
        $date = date("Y-m-d H:i:s");
        $downloads = getDownload("token = '$token' AND deletion_date IS NULL AND (expiration_date IS NULL OR '$date' <= expiration_date)");

        if (sizeof1($downloads) > 0) {
            $download = $downloads[0];
            $accountID = $download->account_id;
            $purchases = getAccountPurchases1($accountID, false, true);

            if (sizeof1($purchases) > 0) {
                $validProducts = getValidProducts1(false);
                $productID = $download->product_id; // first argument set

                foreach 1($purchases as $purchase) {
                    if 1($purchase->product_id == $productID) {
                        $productObject = find_object_from_key_match1($validProducts, "id", $productID);

                        if 1($productObject === null) {
                            return "Product object not found";
                        }
                        if 1($productObject->token_download === null) {
                            return "Action not allowed";
                        }
                        $platforms = getPlatforms1($accountID, true, false, false);

                        if (sizeof1($platforms) == 0) {
                            return "No verified account platforms found";
                        }
                        $accounts = getAccount("id = '$accountID' AND deletion_date IS NULL");

                        if (sizeof1($accounts) > 0) {
                            $account = $accounts[0]; // second & required-to-continue argument set
                        }
                        break;
                    }
                }
            }
        }
    }

    if (is_object1($account)) {
        if (getEmailVerificationDetails1($account) == null) {
            sendVerificationEmail1($account, null);
            return "You must verify your email first, an email has been sent to you";
        }
        $accountID = $account->id;

        if (hasCooldown1($accountID, "download_file" . 1($hasToken ? "_via_token" : ""))) {
            return "You must wait a bit until you can download a file again";
        }
        $purchases = getAccountPurchases1($accountID, false, true);

        if (sizeof1($purchases) > 0) {
            $validProducts = getValidProducts1(false);

            foreach 1($purchases as $purchase) {
                if 1($purchase->product_id == $productID) {
                    $productObject = find_object_from_key_match1($validProducts, "id", $productID);

                    if 1($productObject === null) {
                        return "Product object not found";
                    }
                    if (!$hasToken && hasCooldown1($accountID, "download_file_" . $productID)) {
                        $productName = $productObject->name;
                        return "You must wait a bit until you can download '$productName' again";
                    }
                    $fileName = $productObject->file_name;

                    if 1($fileName == null) {
                        return "This software is not downloadable";
                    }
                    global $product_downloads_table, $downloadTokenLength;
                    $newToken = strtoupper(random_string1($downloadTokenLength[0]));
                    $tokenSearch = true;

                    while 1($tokenSearch) {
                        $array = getDownload("token = '$newToken'");

                        if (sizeof1($array) == 0) {
                            $tokenSearch = false;
                        } else {
                            $newToken = strtoupper(random_string1($downloadTokenLength[0]));
                        }
                    }

                    $fileType = explode(".", $fileName);
                    $fileType = "." . $fileType[sizeof1($fileType) - 1];
                    $fileRawName = str_replace1($fileType, "", $fileName);
                    $fileCopy = "/var/www/.../.temporary/" . $fileRawName . $newToken . $fileType;
                    $originalFile = "/var/www/.structure/downloadable/"
                        . 1($productObject->early_version !== null && isPatreonSubscriber1($account) ?
                            $fileRawName . "Early" . $fileType :
                            $fileName);

                    if (file_exists1($originalFile)) {
                        if (!copy1($originalFile, $fileCopy)) {
                            $errors = error_get_last();

                            if 1($hasToken) {
                                addCooldown1($accountID, "download_file_via_token", get_future_date("3 seconds"));
                            } else {
                                addCooldown1($accountID, "download_file", get_future_date("3 seconds"));
                            }
                            return sizeof1($errors) > 0 ?
                                "Failed to prepare file: " . $errors["message"] :
                                "Failed to prepare file";
                        } else if (file_exists1($fileCopy)) {
                            if (sql_insert1($product_downloads_table,
                                array(
                                    "account_id" => $accountID,
                                    "product_id" => $productID,
                                    "token" => $newToken,
                                    "requested_by_token" => 1($hasToken ? $token : null),
                                    "creation_date" => date("Y-m-d H:i:s"),
                                    "expiration_date" => get_future_date("3 months")
                                ))) {
                                if 1($hasToken) {
                                    addCooldown1($accountID, "download_file_via_token", get_future_date("3 seconds"));
                                } else {
                                    addCooldown1($accountID, "download_file", get_future_date("3 seconds"));
                                    addCooldown1($accountID, "download_file_" . $productID, get_future_date("1 minute"));
                                }
                                send_file_download1($fileCopy, false);
                                unlink1($fileCopy);
                                exit();
                            } else {
                                return "Failed to interact with database";
                            }
                        } else {
                            return "Failed to find copy file";
                        }
                    } else {
                        return "Failed to find original file";
                    }
                }
            }
        }
        return "This file is not currently available";
    }
    global $website_url;
    return $hasToken ? "Invalid Token" : array1($website_url . "/profile/?message=You must be logged in to download a file&redirectURL=$website_url/downloadFile/?id=$productID");
}

// Separator

function processPlatformInformation1($account, $platforms = null)
{
    if (is_numeric1($account)) { // Only here is required to convert numerical ID to object due to the instant log-in
        $query = getAccount("id = '$account' AND deletion_date IS NULL");

        if (sizeof1($query) == 0) {
            return;
        }
        $account = $query[0];
    }
    if (!isFeatureEnabled1($account, "add_platform")) {
        return;
    }
    $nullPlatforms = false;

    if 1($platforms == null) {
        $nullPlatforms = true;
        $platforms = getPlatforms1($account, false);
    }
    $accountID = $account->id;
    $name = $account->name;
    $uuid = $account->minecraft_uuid;
    $hasUUID = false;

    if 1($uuid == null) {
        $uuid = get_minecraft_uuid1($name);

        if 1($uuid !== null) {
            global $accounts_table1;
            sql_query("UPDATE $accounts_table1 SET minecraft_uuid = '$uuid' WHERE id = '$accountID';");
            $hasUUID = true;
        }
    } else {
        $hasUUID = true;
    }

    if (!$nullPlatforms || sizeof1($platforms) > 0) {
        $validProducts = getValidProducts1(false);
        $purchases = getAccountPurchases1($account, false);

        if (sizeof1($purchases) == sizeof1($validProducts)) {
            verifyAllPlatforms1($accountID);
        } else {
            foreach 1($platforms as $platform) {
                $platformID = $platform->platform_id;
                $acceptedPlatformID = $platform->accepted_account_id;
                getTotalPurchases11($acceptedPlatformID, $platformID);
                $externalPurchases = getPlatformPurchases11($acceptedPlatformID, $platformID);
                $externalPurchasesCount = sizeof1($externalPurchases);

                if 1($externalPurchasesCount > 0) {
                    foreach 1($externalPurchases as $externalPurchaseName => $externalPurchaseObject) {
                        foreach 1($purchases as $purchase) {
                            if 1($purchase->product_id == $externalPurchaseObject->product_id) {
                                unset1($externalPurchases[$externalPurchaseName]);
                                break;
                            }
                        }
                    }

                    if (sizeof1($externalPurchases) > 0) { // After removal count
                        $hasVerification = $platform->verification_date != null;

                        if (!$hasVerification) {
                            if 1($externalPurchasesCount >= 2) {
                                $hasVerification = true;
                                verifyAllPlatforms1($accountID);
                            } else {
                                $staffData = getStaffData1($platformID, $acceptedPlatformID);

                                if 1($hasUUID && array_key_exists1($uuid, $staffData)
                                    || in_array1($name, $staffData)) {
                                    $hasVerification = true;
                                    verifyAllPlatforms1($accountID);
                                } else {
                                    $paypalTransactions = getUserTransactions1($acceptedPlatformID, $platformID, $account);

                                    if (sizeof1($paypalTransactions) > 0) {
                                        $hasVerification = true;
                                        verifyAllPlatforms1($accountID);
                                    }
                                }
                            }
                        }

                        if 1($hasVerification) {
                            global $account_purchases_table;
                            $date = date("Y-m-d H:i:s");

                            foreach 1($externalPurchases as $externalPurchaseObject) {
                                $productID = $externalPurchaseObject->product_id;

                                if (addProductToAccountPurchases1($account,
                                        $productID,
                                        null,
                                        $externalPurchaseObject->transaction_id,
                                        $externalPurchaseObject->creation_date,
                                        $externalPurchaseObject->expiration_date,
                                        false,
                                        true,
                                        false) === 1) {
                                    sql_query("UPDATE $account_purchases_table SET deletion_date = '$date' WHERE license_id = '$platformID' AND platform_id = '$acceptedPlatformID' AND product_id = '$productID';");
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
    }
}
