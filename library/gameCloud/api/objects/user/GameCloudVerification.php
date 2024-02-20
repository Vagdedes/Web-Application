<?php

class GameCloudVerification
{

    // Piracy
    public const
        monthly_staff_limit = 31, // One staff per day
        monthly_file_limit = 31, // One file per day
        monthly_ip_address_limit = 256, // 8+ new IPs per day
        monthly_ports_limit = 5461, // Max port limit per server [limit in year divided by months, (65536 / 12)]
        maximum_licenses_per_ip = 6; // An IP should theoretically have one license per platform or at minimum 2 (the real one, and potentially one created by inconsistencies)

    // States
    public const
        ordinary_verification_value = 1,
        skip_analysis_value = 2,
        suspended_user_value = 0,
        failed_analysis_value = -1;

    // Structure
    public const managed_license_types = array(
        /*0*/
        "license", // license bans
        /*1*/
        "file", // file bans
        /*2*/
        "analysis", // mass check exemption
        /*3*/
        "customer-support", // customer-support feature bans
    );

    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function isVerified(int|string $fileID, int|string $productID, string $ipAddress): int
    {
        global $license_management_table;
        $result = $this::ordinary_verification_value;
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();

        // Cache
        $cacheKey = array(
            $platform,
            $licenseID,
            $fileID,
            $productID,
            $ipAddress,
            self::class
        );
        $cache = get_key_value_pair($cacheKey);

        if (is_numeric($cache)) {
            return $cache;
        }

        // Live
        $query = get_sql_query(
            $license_management_table,
            array("type", "number", "expiration_date"),
            array(
                array("platform_id", $platform),
                array("deletion_date", null),
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

            foreach ($query as $row) {
                $expiration_date = $row->expiration_date;

                if ($expiration_date === null || $date < $expiration_date) {
                    $type = $row->type;
                    $number = $row->number;

                    if ($number == $licenseID) {
                        if ($type == $this::managed_license_types[0]) { // license
                            $result = $this::suspended_user_value;
                            break;
                        }
                        if ($result === $this::ordinary_verification_value && $type == $this::managed_license_types[2]) { // mass exemption
                            $result = $this::skip_analysis_value;
                            // Do not stop as this is not a reason to unverify the user
                        }
                    }
                    if ($type == $this::managed_license_types[1] && $number == $fileID) { // file
                        $result = $this::suspended_user_value;
                        break;
                    }
                }
            }
        }

        // Result
        if ($result > 0
            && $result !== $this::skip_analysis_value
            && !$this->isAnalysisVerified($productID, $ipAddress)) {
            $result = $this::failed_analysis_value;
        }
        set_key_value_pair($cacheKey, $result, "1 hour");
        return $result;
    }

    private function isAnalysisVerified(int|string $productID, string $ipAddress): bool
    {
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $cacheKey = array(
            __METHOD__,
            $platform,
            $licenseID
        );
        $cache = get_key_value_pair($cacheKey);

        if (is_bool($cache)) {
            return $cache;
        } else {
            global $verifications_table, $license_management_table;
            $query = get_sql_query(
                $license_management_table,
                array("id"),
                array(
                    array("type", "=", $this::managed_license_types[2]),
                    array("platform_id", "=", $platform),
                    array("number", "=", $licenseID),
                    array("product_id", "=", $productID),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
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
                                $this->addLicenseManagement(null, $this::managed_license_types[0], "maximumLicensesPerIP", null, true);
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
                                    $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyIPAddressLimit", null, true);
                                    $result = false;
                                    break;
                                }
                            }
                            if (!in_array($port, $ports)) {
                                $ports[] = $port;

                                if (sizeof($ports) === $this::monthly_ports_limit) {
                                    $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyPortsLimit", null, true);
                                    $result = false;
                                    break;
                                }
                            }
                            if (!in_array($file, $files)) {
                                $files[] = $file;

                                if (sizeof($files) === $this::monthly_file_limit) {
                                    $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyFileLimit", null, true);
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
                                    $this->addLicenseManagement(null, $this::managed_license_types[0], "monthlyStaffLimit", null, true);
                                    $result = false;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            set_key_value_pair($cacheKey, $result, "1 hour");
            return $result;
        }
    }

    public function addLicenseManagement(int|string|null $productID, string $type,
                                         ?string         $reason,
                                         ?string         $duration, bool $automated): bool
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
        $query = get_sql_query(
            $license_management_table,
            array("id"),
            array(
                array("type", $type),
                array("number", $licenseID),
                array("platform_id", $platform),
                array("deletion_date", null),
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

    public function removeLicenseManagement(int|string|null $productID, string $type): bool
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
                array("deletion_Date", null),
                $productID !== null ? array("product_id", $productID) : "",
            ),
            null,
            1
        );

        if (!empty($query)
            && !set_sql_query(
                $license_management_table,
                array(
                    "deletion_date" => get_current_date()
                ),
                array(
                    array("id", $query[0]->id)
                ),
                null,
                1
            )) {
            return false;
        }
        $this->user->clearMemory(self::class, function ($value) {
            return is_numeric($value);
        });
        return true;
    }
}
