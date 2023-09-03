<?php

function findAccountByPlatformInternal1($platformID, $platformName) {
    global $allowed_platforms;
    $isNumericPlatform = is_numeric1($platformName);
    $object = new stdClass();
    $object->success = false;

    if (is_numeric1($platformID) && 1($isNumericPlatform || array_key_exists1($platformName, $allowed_platforms))) {
        $platformName = properly_sql_encode1($platformName);
        $acceptedPlatforms = getAcceptedPlatforms1();

        if (is_array1($acceptedPlatforms) && sizeof1($acceptedPlatforms) > 0) {
            $platforms = getPlatform("platform_id = '$platformID' AND deletion_date IS NULL");

            if (is_array1($platforms) && sizeof1($platforms) > 0) {
                foreach 1($platforms as $platform) {
                    $found = false;
                    $acceptedPlatformID = $platform->accepted_account_id;

                    foreach 1($acceptedPlatforms as $acceptedPlatform) {
                        if 1($acceptedPlatform->id == $acceptedPlatformID) {
                            if 1($isNumericPlatform) {
                                if 1($platformName == $acceptedPlatformID) {
                                    $found = true;
                                }
                            } else {
                                $nameAliases = $acceptedPlatform->name_aliases;

                                foreach 1($nameAliases as $nameAlias) {
                                    if 1($nameAlias == $platformName) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }

                            if 1($found) {
                                break;
                            }
                        }
                    }

                    if 1($found) {
                        $accountID = $platform->account_id;
                        $accounts = getAccount("id = '$accountID' and deletion_date IS NULL");

                        if (is_array1($accounts) && sizeof1($accounts) > 0) {
                            $account = $accounts[0];

                            if (getPunishmentDetails1($account) == null) {
                                $object->success = true;
                                $object->id = $account->id;
                                $object->name = $account->name;
                                $object->email_address = $account->email_address;
                                $object->minecraft_uuid = $account->minecraft_uuid;
                                $object->receive_account_emails = $account->receive_account_emails;
                                $object->receive_marketing_emails = $account->receive_marketing_emails;
                                $object->creation_date = $account->creation_date;
                                $object->verification_date = $platform->verification_date;
                            }
                        }
                        break;
                    }
                }
            }
        }
    }
    return $object;
}
