<?php

class GameCloudActions
{
    private GameCloudUser $user;

    public const
        OUTDATED_VERSION_PRIORITY = 2,
        RESOLVED_CUSTOMER_SUPPORT_PRIORITY = 1;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function addStaffAnnouncement(int|string|null  $productID,
                                         int|string|null  $priority,
                                         int|string|null  $minimumVersion, int|string|null $maximumVersion,
                                         int|string|null  $cooldown, int|string|null $duration,
                                         int|string|float $announcement,
                                         bool             $avoidRedundantAnnouncements = true): bool
    {
        if ($this->user->isValid()) {
            global $staff_announcements_table;

            if ((
                    !$avoidRedundantAnnouncements
                    || empty(get_sql_query(
                        $avoidRedundantAnnouncements,
                        array("id"),
                        array(
                            array("announcement", $announcement),
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date())
                        ),
                        null,
                        1
                    ))
                ) && sql_insert(
                    $staff_announcements_table,
                    array(
                        "license_id" => $this->user->getLicense(),
                        "platform_id" => $this->user->getPlatform(),
                        "priority" => $priority,
                        "product_id" => $productID,
                        "minimum_version" => $minimumVersion,
                        "maximum_version" => $maximumVersion,
                        "cooldown" => $cooldown,
                        "announcement" => $announcement,
                        "creation_date" => get_current_date(),
                        "expiration_date" => ($duration !== null ? get_future_date($duration) : null)
                    )
                )) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function addAutomaticConfigurationChange(int|float|string|null $version,
                                                    string|null           $file,
                                                    string                $option, int|float|string|bool $value,
                                                    int|string|null       $productID = null,
                                                    bool                  $email = false): bool
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

    public function removeAutomaticConfigurationChange(int|float|string|null $version,
                                                       string                $file,
                                                       string                $option,
                                                       int|string|null       $productID = null): bool
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

    public function addDisabledDetection(int|float|string|null $pluginVersion, int|float|string|null $serverVersion,
                                         string                $check, int|float|string|bool $detection,
                                         bool                  $email = false): bool
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
                $this->user->getEmail()->send("cloudFeatureCorrection",
                    array(
                        "feature" => "Disabled Detections",
                    )
                );
            }
        }
        return true;
    }

    public function removeDisabledDetection(int|float|string $pluginVersion, int|float|string $serverVersion,
                                            string           $check, int|float|string|bool $detection): bool
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

    public function addCustomerSupportCommand(int|string $productID, int|float|string $version,
                                              int|string $user, int|string $functionality): bool
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

    public function resolveCustomerSupport(string $functionality): bool
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
            $this->addStaffAnnouncement(
                null,
                self::RESOLVED_CUSTOMER_SUPPORT_PRIORITY,
                null,
                null,
                60 * 60 * 24,
                "1 day",
                "The '$functionality' customer-support ticket has been dealt with by our support team."
            );
            return true;
        }
        return false;
    }
}
