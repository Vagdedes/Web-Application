<?php

class GameCloudVerification
{
    private const cache_key = "game-cloud-verification";

    // Piracy
    public const
        timeout_limit = 5,
        monthly_staff_limit = 31, // One staff per day
        monthly_file_limit = 31, // One file per day
        monthly_ip_address_limit = 256, // 8+ new IPs per day
        monthly_ports_limit = 5461, // Max port limit per server [limit in year divided by months, (65536 / 12)]
        maximum_licenses_per_ip = 6; // An IP should theoretically have one license per platform or at minimum 2 (the real one, and potentially one created by inconsistencies)

    // States
    public const
        ordinary_verification_value = 1,
        skip_mass_verification_value = 2,
        suspended_user_value = 0,
        failed_mass_verification_value = -1,
        timeout_suspended_value = -2,
        missing_platform_information = -3,
        missing_license_information = -4;

    // Structure
    public const managed_license_types = array(
        /*0*/
        "license", // license bans
        /*1*/
        "file", // file bans
        /*2*/
        "mass", // mass check exemption
        /*3*/
        "customer-support", // customer-support feature bans
        /*4*/
        "timeout", // system timeouts
        /*5*/
        "server-limit" // server ip/port limits
    );

    private GameCloudUser $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function isVerified($fileID, $productID, $ipAddress): int
    {
        $licenseID = $this->user->getLicense();

        if ($licenseID === null) {
            return $this::missing_license_information;
        }
        $platform = $this->user->getPlatform();

        if ($platform === null) {
            return $this::missing_platform_information;
        }
        global $license_management_table;
        $result = $this::ordinary_verification_value;

        // Cache
        $cacheKey = array(
            $platform,
            $licenseID,
            $fileID,
            $productID,
            $ipAddress,
            $this::cache_key
        );
        $cache = get_key_value_pair($cacheKey);

        if (is_numeric($cache)) {
            return $cache;
        }

        // Live
        $query = get_sql_query(
            $license_management_table,
            array("type", "number", "extra", "expiration_date"),
            array(
                array("platform_id", $platform),
                null,
                array("number", "=", $licenseID, 0),
                array("number", $fileID),
                null,
                null,
                array("product_id", "=", $productID, 0),
                array("product_id", null),
                null,
            ),
            null,
            1
        );

        if (!empty($query)) {
            $date = get_current_date();
            $licenseType = $this::managed_license_types[0];
            $fileType = $this::managed_license_types[1];
            $massType = $this::managed_license_types[2];
            $timeoutType = $this::managed_license_types[4];
            $timeoutCounter = 0;

            foreach ($query as $row) {
                $expiration_date = $row->expiration_date;

                if ($expiration_date === null || $date < $expiration_date) {
                    $type = $row->type;
                    $number = $row->number;

                    if ($number == $licenseID) {
                        if ($type == $licenseType) { // license
                            $result = $this::suspended_user_value;
                            break;
                        }
                        if ($type == $timeoutType && $row->extra == $ipAddress) { // timeout
                            $timeoutCounter++;

                            if ($timeoutCounter === $this::timeout_limit) { // timeout max
                                $result = $this::timeout_suspended_value;
                                break;
                            } else {
                                $result = $this::suspended_user_value;
                            }
                        } else if ($result === $this::ordinary_verification_value && $type == $massType) { // mass exemption
                            $result = $this::skip_mass_verification_value;
                            // Do not stop as this is not a reason to unverify the user
                        }
                    }
                    if ($type == $fileType && $number == $fileID) { // file
                        $result = $this::suspended_user_value;
                        break;
                    }
                }
            }
        }

        // Result
        if ($result > 0
            && $result !== $this::skip_mass_verification_value
            && !$this->isMassVerified($productID, $ipAddress)) {
            $result = $this::failed_mass_verification_value;
        }
        set_key_value_pair($cacheKey, $result, "1 hour");
        return $result;
    }

    private function isMassVerified($productID, $ipAddress): bool
    {
        global $verifications_table, $license_management_table;
        $date = get_current_date();
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $massType = $this::managed_license_types[2];
        $query = get_sql_query(
            $license_management_table,
            array("id"),
            array(
                array("type", "=", $massType),
                array("platform_id", "=", $platform),
                array("number", "=", $licenseID),
                array("product_id", "=", $productID),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null,
            ),
            null,
            1
        );
        $result = true;

        if (empty($query)) {
            $query = get_sql_query(
                $verifications_table,
                array("license_id", "platform_id"),
                array(
                    array("ip_address", $ipAddress),
                    array("last_access_date", ">=", get_past_date("1 month")),
                )
            );

            if (!empty($query)) {
                $uniqueLicenses = array();

                foreach ($query as $row) {
                    $rowPlatform = $row->platform_id;
                    $uniqueLicense = ($rowPlatform === null ? "" : $rowPlatform) . "-" . $row->license_id;

                    if (!in_array($uniqueLicense, $uniqueLicenses)) {
                        $uniqueLicenses[] = $uniqueLicense;

                        if (sizeof($uniqueLicenses) === $this::maximum_licenses_per_ip) {
                            $this->addLicenseManagement(null, $this::managed_license_types[0], "maximumLicensesPerIP", null, null, true);
                            $result = false;
                            break;
                        }
                    }
                }
            }

            // Separator

            if ($result) {
                $query = get_sql_query(
                    $verifications_table,
                    array("ip_address", "port", "file_id"),
                    array(
                        array("license_id", $licenseID),
                        array("platform_id", $platform),
                        array("last_access_date", ">", get_past_date("1 month")),
                    )
                );

                if (!empty($query)) {
                    $ipAddresses = array();
                    $ports = array();
                    $files = array();

                    foreach ($query as $row) {
                        $ipAddress = $row->ip_address;
                        $port = $row->port;
                        $file = $row->file_id;

                        if (!in_array($ipAddress, $ipAddresses)) {
                            $ipAddresses[] = $ipAddress;

                            if (sizeof($ipAddresses) == $this::monthly_ip_address_limit) {
                                $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyIPAddressLimit", null, null, true);
                                $result = false;
                                break;
                            }
                        }
                        if (!in_array($port, $ports)) {
                            $ports[] = $port;

                            if (sizeof($ports) === $this::monthly_ports_limit) {
                                $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyPortsLimit", null, null, true);
                                $result = false;
                                break;
                            }
                        }
                        if (!in_array($file, $files)) {
                            $files[] = $file;

                            if (sizeof($files) === $this::monthly_file_limit) {
                                $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyFileLimit", null, null, true);
                                $result = false;
                                break;
                            }
                        }
                    }
                }
            }

            // Separator

            if ($result) {
                global $staff_players_table;
                $query = get_sql_query(
                    $staff_players_table,
                    array("uuid"),
                    array(
                        array("license_id", $licenseID),
                        array("platform_id", $platform),
                        array("last_access_date", ">", get_past_date("1 month")),
                    )
                );

                if (!empty($query)) {
                    $uuids = array();

                    foreach ($query as $row) {
                        $uuid = $row->uuid;

                        if (!in_array($uuid, $uuids)) {
                            $uuids[] = $uuid;

                            if (sizeof($uuids) == $this::monthly_staff_limit) {
                                $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyStaffLimit", null, null, true);
                                $result = false;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $this->addLicenseManagement(null, $massType, null, null, "1 day", true);
        return $result;
    }

    public function addLicenseManagement($productID, $type, $reason, $extra, $duration, $automated, $newRow = false): bool
    {
        if (!in_array($type, $this::managed_license_types)) {
            return false;
        }
        global $license_management_table;

        if ($duration !== null) {
            $duration = get_future_date($duration);
        }
        $date = get_current_date();
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $query = $newRow ? null : get_sql_query(
            $license_management_table,
            array("id"),
            array(
                array("type", $type),
                array("number", $licenseID),
                array("platform_id", $platform),
                $productID !== null ? array("product_id", $productID) : "",
            ),
            null,
            1
        );

        if (empty($query)) {
            if (!sql_insert(
                $license_management_table,
                array(
                    "type" => $type,
                    "number" => $licenseID,
                    "platform_id" => $platform,
                    "product_id" => $productID,
                    "reason" => $reason,
                    "extra" => $extra,
                    "creation_date" => $date,
                    "expiration_date" => $duration,
                    "automated" => $automated
                )
            )) {
                return false;
            }
        } else if (!set_sql_query(
            $license_management_table,
            array(
                "reason" => $reason,
                "automated" => $automated,
                "expiration_date" => $duration,
                "creation_date" => $date
            ),
            array(
                array("id", $query[0]->id)
            ),
            null,
            1
        )) {
            return false;
        }
        return true;
    }

    public function removeLicenseManagement($productID, $type): bool
    {
        if (!in_array($type, $this::managed_license_types)) {
            return false;
        }
        global $license_management_table;
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $query = get_sql_query(
            $license_management_table,
            array("id"),
            array(
                array("type", $type),
                array("number", $licenseID),
                array("platform_id", $platform),
                $productID !== null ? array("product_id", $productID) : "",
            ),
            null,
            1
        );

        if (!empty($query)
            && !delete_sql_query(
                $license_management_table,
                array(
                    array("id", $query[0]->id)
                )
            )) {
            return false;
        }
        $this->user->clearMemory($this::cache_key);
        return true;
    }

    public function timeoutAccess($version, $productID, $ipAddress, $reason, $exit = true)
    {
        $this->addLicenseManagement(
            $productID,
            $this::managed_license_types[4],
            $version . "-" . $reason,
            $ipAddress,
            "10 minutes",
            true,
            true,
        );

        if ($exit) {
            exit();
        }
    }
}
