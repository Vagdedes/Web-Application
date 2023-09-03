<?php

class GameCloudActions
{
    private GameCloudUser $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function addAutomaticConfigurationChange($version, $file, $option, $value, $productID = null, $email = false): bool
    {
        global $configuration_changes_table;
        $file = str_replace(" ", "_", $file);
        $option = str_replace(" ", "_", $option);
        $licenseID = $this->user->getLicense();
        $platform = $this->user->getPlatform();
        $query = get_sql_query(
            $configuration_changes_table,
            array("id"),
            array(
                array("license_id", $licenseID),
                array("platform_id", $platform),
                array("product_id", $productID),
                array("version", $version),
                array("abstract_option", $option),
                array("file_name", $file),
            ),
            null,
            1
        );

        if (!empty($query)) {
            if (!set_sql_query(
                $configuration_changes_table,
                array(
                    "completed_ip_addresses" => null,
                    "value" => $value === null ? "" : $value,
                    "version" => $version
                ),
                array(
                    array("id", $query[0]->id)
                ),
                null,
                1
            )) {
                return false;
            }
        } else if (!sql_insert(
            $configuration_changes_table,
            array(
                "platform_id" => $platform,
                "license_id" => $licenseID,
                "product_id" => $productID,
                "version" => $version,
                "file_name" => $file,
                "abstract_option" => $option,
                "value" => $value
            ))) {
            return false;
        }
        if ($licenseID !== null && $platform !== null) {
            $this->resolveCustomerSupport(explode(".", $option, 2)[0]);

            if ($email) {
                $this->user->getEmail()->send("cloudFeatureCorrection",
                    array(
                        "feature" => "Automatic Configuration Changes",
                    )
                );
            }
        }
        return true;
    }

    public function removeAutomaticConfigurationChange($version, $file, $option, $productID = null): bool
    {
        global $configuration_changes_table;
        $file = str_replace(" ", "_", $file);
        $option = str_replace(" ", "_", $option);
        $licenseID = $this->user->getLicense();
        $platform = $this->user->getPlatform();
        $query = get_sql_query(
            $configuration_changes_table,
            array("id"),
            array(
                array("license_id", $licenseID),
                array("platform_id", $platform),
                array("product_id", $productID),
                array("version", $version),
                array("abstract_option", $option),
                array("file_name", $file)
            ),
            null,
            1
        );

        if (!empty($query)
            && !delete_sql_query(
                $configuration_changes_table,
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

    public function addDisabledDetection($pluginVersion, $serverVersion, $check, $detection, $email = false): bool
    {
        global $disabled_detections_table;
        $detection = str_replace(" ", "__", $detection);
        $licenseID = $this->user->getLicense();
        $platform = $this->user->getPlatform();
        $query = get_sql_query(
            $disabled_detections_table,
            array("id", "detections"),
            array(
                array("license_id", $licenseID),
                array("platform_id", $platform),
                array("plugin_version", $pluginVersion),
                array("server_version", $serverVersion)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $groupRebuild = "";
            $foundGroup = false;

            foreach (explode(" ", $query->detections) as $group) {
                if (strlen($group) > 0) {
                    $detections = explode("|", $group);

                    if ($check == $detections[0]) {
                        $foundGroup = true;
                        unset($detections[0]);

                        if (!in_array($detection, $detections)) {
                            $group .= ("|" . $detection);
                        }
                    }
                    $groupRebuild .= ($group . " ");
                }
            }
            if (!$foundGroup) {
                $groupRebuild .= (" " . $check . "|" . $detection);
            }

            if (!empty($groupRebuild)) {
                if (!set_sql_query(
                    $disabled_detections_table,
                    array(
                        "detections" => $groupRebuild
                    ),
                    array(
                        array("id", $query->id)
                    ),
                    null,
                    1
                )) {
                    return false;
                }
            } else if (!delete_sql_query(
                $disabled_detections_table,
                array(
                    array("id", $query->id)
                ),
                null,
                1
            )) {
                return false;
            }
        } else if (!sql_insert(
            $disabled_detections_table,
            array(
                "platform_id" => $platform,
                "license_id" => $licenseID,
                "plugin_version" => $pluginVersion,
                "server_version" => $serverVersion,
                "detections" => ($check . "|" . $detection)
            ))) {
            return false;
        }
        if ($licenseID !== null && $platform !== null) {
            $this->resolveCustomerSupport($check);

            if ($email) {
                $this->user->getEmail()->send($platform, $licenseID, "cloudFeatureCorrection",
                    array(
                        "feature" => "Disabled Detections",
                    )
                );
            }
        }
        return true;
    }

    public function removeDisabledDetection($pluginVersion, $serverVersion, $check, $detection): bool
    {
        global $disabled_detections_table;
        $detection = str_replace(" ", "__", $detection);
        $licenseID = $this->user->getLicense();
        $platform = $this->user->getPlatform();
        $query = get_sql_query(
            $disabled_detections_table,
            array("id", "detections"),
            array(
                array("license_id", $licenseID),
                array("platform_id", $platform),
                array("plugin_version", $pluginVersion),
                array("server_version", $serverVersion)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $groupRebuild = "";
            $foundGroup = false;

            foreach (explode(" ", $query->detections) as $group) {
                if (strlen($group) > 0) {
                    $detections = explode("|", $group);
                    $countGroup = true;

                    if ($check == $detections[0]) {
                        $foundGroup = true;
                        unset($detections[0]);

                        if (in_array($detection, $detections)) {
                            $group = str_replace("|" . $detection, "", $group);

                            if (sizeof($detections) === 1) {
                                $countGroup = false;
                            }
                        }
                    }
                    if ($countGroup) {
                        $groupRebuild .= ($group . " ");
                    }
                }
            }
            if (!$foundGroup) {
                $groupRebuild .= (" " . $check . "|" . $detection);
            }

            if (!empty($groupRebuild)) {
                if (!set_sql_query(
                    $disabled_detections_table,
                    array(
                        "detections" => $groupRebuild
                    ),
                    array(
                        array("id", $query->id)
                    ),
                    null,
                    1
                )) {
                    return false;
                }
            } else if (!delete_sql_query(
                $disabled_detections_table,
                array(
                    array("id", $query->id)
                ),
                null,
                1
            )) {
                return false;
            }
        }
        return true;
    }

    public function addCustomerSupportCommand($productID, $version, $user, $functionality): bool
    {
        global $customer_support_commands_table;

        if (sql_insert(
            $customer_support_commands_table,
            array(
                "platform_id" => $this->user->getPlatform(),
                "license_id" => $this->user->getLicense(),
                "product_id" => $productID,
                "version" => $version,
                "user" => $user,
                "functionality" => $functionality,
                "creation_date" => get_current_date(),
                "expiration_date" => get_future_date("1 day")
            ))) {
            return true;
        }
        return false;
    }

    public function resolveCustomerSupport($functionality): bool
    {
        global $customer_support_table;

        if (set_sql_query(
            $customer_support_table,
            array(
                "resolution_date" => get_current_date()
            ),
            array(
                array("platform_id", $this->user->getPlatform()),
                array("license_id", $this->user->getLicense()),
                array("functionality", $functionality),
                array("resolution_date", null)
            ),
            null,
            1
        )) {
            $customerSupport = new CustomerSupport();
            $customerSupport->clearCache();
            return true;
        }
        return false;
    }
}
