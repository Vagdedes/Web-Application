<?php

class GameCloudVerification
{

    // States
    private const
        ordinary_verification_value = 1,
        suspended_user_value = 0,
        suspended_ip_value = -1;

    // Structure
    public const managed_license_types = array(
        /*0*/
        "license", // license bans
        /*1*/
        "file", // file bans,
        /*2*/
        "ip-address", // ip management
    );

    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function isVerified(int|string $fileID, int|string $productID, string $ipAddress): int
    {
        $result = $this::ordinary_verification_value;
        $licenseID = $this->user->getLicense();
        $date = get_current_date();
        $query = get_sql_query(
            GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
            array("type", "number", "expiration_date"),
            array(
                array("platform_id", $this->user->getPlatform()),
                array("deletion_date", null),
                null,
                array("number", "=", $licenseID, 0),
                array("number", "=", $fileID, 0),
                array("number", null),
                null,
                null,
                array("product_id", "=", $productID, 0),
                array("product_id", null),
                null,
                null,
                array("expiration_date", ">=", $date, 0),
                array("expiration_date", null),
                null
            ),
            null,
            1
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $type = $row->type;
                $number = $row->number;

                if (($number == $licenseID
                        || $number === null)
                    && $type == $this::managed_license_types[0]) { // license
                    $result = $this::suspended_user_value;
                    break;
                }
                if ($type == $this::managed_license_types[1] && $number == $fileID) { // file
                    $result = $this::suspended_user_value;
                    break;
                }
            }
        }
        if ($result === $this::ordinary_verification_value) {
            $query = get_sql_query(
                GameCloudVariables::IP_MANAGEMENT_TABLE,
                array("ip_allow"),
                array(
                    array("ip_address", $ipAddress),
                    array("platform_id", $this->user->getPlatform()),
                    array("license_id", $licenseID),
                    array("deletion_date", null),
                    null,
                    array("product_id", "=", $productID, 0),
                    array("product_id", null),
                    null,
                    null,
                    array("expiration_date", ">=", $date, 0),
                    array("expiration_date", null),
                    null
                ),
                null,
                1
            );

            if (!empty($query)
                && $query[0]->ip_allow === null) {
                $result = $this::suspended_ip_value;
            }
        }
        return $result;
    }

    public function addLicenseManagement(int|string|null $productID,
                                         string          $type,
                                         ?string         $reason,
                                         ?string         $duration,
                                         ?string         $ipAddress): bool
    {
        if (!in_array($type, $this::managed_license_types)) {
            return false;
        }
        $isIpManagement = $type === $this::managed_license_types[2];

        if ($isIpManagement
            && $ipAddress === null) {
            return false;
        }
        if ($duration !== null) {
            $duration = get_future_date($duration);
        }
        $date = get_current_date();
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $table = $isIpManagement
            ? GameCloudVariables::IP_MANAGEMENT_TABLE
            : GameCloudVariables::LICENSE_MANAGEMENT_TABLE;
        $query = get_sql_query(
            $table,
            array("id"),
            array(
                $isIpManagement
                    ? ""
                    : array("type", $type),
                $isIpManagement
                    ? array("license_id", $licenseID)
                    : array("number", $licenseID),
                array("platform_id", $platform),
                array("deletion_date", null),
                $productID !== null
                    ? array("product_id", $productID)
                    : "",
            ),
            null,
            1
        );

        if (empty($query)) {
            $insert = $isIpManagement
                ? array(
                    "license_id" => $licenseID,
                    "platform_id" => $platform,
                    "product_id" => $productID,
                    "ip_address" => $ipAddress,
                    "reason" => $reason,
                    "creation_date" => $date,
                    "expiration_date" => $duration
                )
                : array(
                    "type" => $type,
                    "number" => $licenseID,
                    "platform_id" => $platform,
                    "product_id" => $productID,
                    "reason" => $reason,
                    "creation_date" => $date,
                    "expiration_date" => $duration
                );
            if (!sql_insert(
                $table,
                $insert
            )) {
                return false;
            }
        } else if (!set_sql_query(
            $table,
            array(
                "reason" => $reason,
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
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $isIpManagement = $type === $this::managed_license_types[2];
        $table = $isIpManagement
            ? GameCloudVariables::IP_MANAGEMENT_TABLE
            : GameCloudVariables::LICENSE_MANAGEMENT_TABLE;
        $query = get_sql_query(
            $table,
            array("id"),
            array(
                $isIpManagement
                    ? ""
                    : array("type", $type),
                $isIpManagement
                    ? array("license_id", $licenseID)
                    : array("number", $licenseID),
                array("platform_id", $platform),
                array("deletion_date", null),
                $productID !== null
                    ? array("product_id", $productID)
                    : "",
            ),
            null,
            1
        );

        if (!empty($query)
            && !set_sql_query(
                $table,
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
        return true;
    }

}
