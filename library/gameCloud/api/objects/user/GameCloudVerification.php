<?php

class GameCloudVerification
{

    // States
    public const
        ordinary_verification_value = 1,
        suspended_user_value = 0;

    // Structure
    public const managed_license_types = array(
        /*0*/
        "license", // license bans
        /*1*/
        "file" // file bans
    );

    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function isVerified(int|string $fileID, int|string $productID): int
    {
        $result = $this::ordinary_verification_value;
        $licenseID = $this->user->getLicense();

        $query = get_sql_query(
            GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
            array("type", "number", "expiration_date"),
            array(
                array("platform_id", $this->user->getPlatform()),
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

                    if ($number == $licenseID
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
        }
        return $result;
    }

    public function addLicenseManagement(int|string|null $productID, string $type,
                                         ?string         $reason,
                                         ?string         $duration, bool $automated = false): bool
    {
        if (!in_array($type, $this::managed_license_types)) {
            return false;
        }
        if ($duration !== null) {
            $duration = get_future_date($duration);
        }
        $date = get_current_date();
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $query = get_sql_query(
            GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
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
                GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
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
            GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
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
        $platform = $this->user->getPlatform();
        $licenseID = $this->user->getLicense();
        $query = get_sql_query(
            GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
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
                GameCloudVariables::LICENSE_MANAGEMENT_TABLE,
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
