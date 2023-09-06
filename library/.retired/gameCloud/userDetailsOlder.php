<?php

$id = get_form_get("id");

if (is_numeric($id) && $id < 0) {
    $session = getAccountSession1();

    if (is_object($session) && $session->administrator != null) {
        $id = abs($id);
        $query = sql_query("SELECT ip_address, port, license_id, file_id, user_agent, access FROM $verificationsTable1 WHERE license_id = '$id';");

        if ($query != null & $query->num_rows > 0) {
            $limit = get_form_get("limit");

            if (!is_numeric($limit)) {
                $limit = 100;
            } else if ($limit <= 0) {
                $limit = 2147483647;
            }
            $firstLoadDate = getFirstLoadDate1($id);
            $verifiedDefault = isVerified1($id, null, null, null);
            $suspendedUser = $verifiedDefault == 0;
            $massCheckExemption = $verifiedDefault == $skip_mass_verification_value;
            $suspended = $suspendedUser ? "Yes" : "No";
            $exemptedFromMassCheck = $massCheckExemption ? "Yes" : "No";
            $detected = !isMassVerified1($id, null) ? "Yes" : "No";
            $platform = getPurchasePlatform1($id);
            $hasPlatform = $platform != null;
            $platformArgument = $hasPlatform ? "&platform=$platform" : "";
            $websiteAccount = $hasPlatform ? getAccountByPlatform1($id, $platform) : null;
            $hasWebsiteAccount = $websiteAccount != null && is_object($websiteAccount) && $websiteAccount->success == true;
            $hasVerifiedWebsiteAccount = $hasWebsiteAccount && $websiteAccount->verification_date != null;
            $successes = 0;
            $failures = 0;
            $timeouts = 0;
            $uniquePlayers = array();
            $uniqueFiles = array();
            $suspendedFiles = array();
            $suspendedFilesList = array();
            $uniqueServers = array();
            $uniqueVersions = array();
            $uniqueMotds = array();
            $uniquePorts = array();

            echo "<style>
                body {
                    overflow: auto;
                    font-family: Verdana;
                    background-size: 100%;
                    background-color: #212121;
                    color: #eee;
                }
                </style>";

            if (isset($_POST["updateServerLimit"])) {
                $customLimit = isset($_POST["limit"]);
                $explode = $customLimit ? null : explode(" ", $_POST["updateServerLimit"]);
                $newLimit = $customLimit ? $_POST["limit"] : $explode[sizeof($explode) - 1];

                if ($customLimit) {
                    if (!is_numeric($newLimit)) {
                        $newLimit = 1;
                    } else {
                        $newLimit = max($newLimit, 1);
                    }
                }
                manageLicense2($id, $managed_license_types[5], $newLimit, null, false);
            } else if (isset($_POST["createSynCustomer"])) {
                createExtraFunctionalityUser2($id, $spartan_syn_key1);
            } else if (isset($_POST["unbanUser"])) {
                $date = date("Y-m-d H:i:s");
                sql_query("UPDATE $license_management_table SET expiration_date = '$date' WHERE number = '$id' AND type = 'license';");
            } else if (isset($_POST["addMassExemption"])) {
                setMassVerificationCooldown1($id, null, false);
            } else if ($hasWebsiteAccount && isset($_POST["verifyWebsiteAccount"])) {
                $accountID = $websiteAccount->id;
                privateFileGetContents1("https://vagdedes.com/account/api/verifyPlatform/?accountID=$accountID&platformID=$id&platform=$platform&force=true");
            }

            // Separator
            $specificQuery = sql_query("SELECT number, expiration_date FROM $license_management_table WHERE type = 'file';");

            if (isset($specificQuery->num_rows) & $specificQuery->num_rows > 0) {
                $date = date("Y-m-d H:i:s");

                while ($row = $specificQuery->fetch_assoc()) {
                    $expiration_date = $row["expiration_date"];

                    if ($expiration_date == null || $date <= $expiration_date) {
                        array_push($suspendedFilesList, $row["number"]);
                    }
                }
            }

            // Separator
            echo "<b>Players</b><br>";

            while ($row = $query->fetch_assoc()) {
                $user_agent = $row["user_agent"];
                $file_id = $row["file_id"];
                $server = $row["ip_address"];

                if (sizeof($uniquePlayers) < $limit) {
                    foreach (explode(" ", str_replace(",", "", str_replace("(", "", str_replace(")", "", $row["user_agent"])))) as $word) {
                        $split = explode("|", $word);
                        $size = sizeof($split);

                        if ($size == 2) {
                            $uuid = $split[0];
                            $word = $uuid . " " . $split[1];

                            if (strlen($word) >= 36 && !in_array($uuid, $uniquePlayers)) {
                                array_push($uniquePlayers, $uuid);
                                echo $word . "<br>";
                            }
                        } else if ($size == 3) {
                            $name = $split[0];
                            $uuid = $split[1];
                            $word = $name . " " . $uuid . " " . $split[2];

                            if (strlen($word) >= 36 && !in_array($uuid, $uniquePlayers)) {
                                array_push($uniquePlayers, $uuid);
                                echo $word . "<br>";
                            }
                        }
                    }
                }

                if (!in_array($file_id, $uniqueFiles) && !in_array($file_id, $suspendedFiles)) {
                    array_push($uniqueFiles, $file_id);

                    if (in_array($file_id, $suspendedFilesList)) {
                        array_push($suspendedFiles, $file_id);
                    }
                }

                if (sizeof($uniqueServers) < $limit && !in_array($server, $uniqueServers)) {
                    array_push($uniqueServers, $server);
                    $uniquePorts[$server] = array();
                }
                $rowPort = $row["port"];

                if ($rowPort != null && !in_array($rowPort, $uniquePorts[$server])) {
                    array_push($uniquePorts[$server], $rowPort);
                }

                if ($row["access"] == 1) {
                    $successes += 1;
                } else {
                    $failures += 1;
                }
            }
            if (sizeof($uniquePlayers) == 0) {
                echo "None";
            }

            // Separator
            $size = sizeof($uniqueServers);

            if ($size > 0) {
                $query = sql_query("SELECT ip_address, motd FROM minecraft.serverSpecifications WHERE motd IS NOT NULL ORDER BY id DESC;");

                if (isset($query->num_rows) && $query->num_rows > 0) {
                    while ($row = $query->fetch_assoc()) {
                        $rowServer = $row["ip_address"];

                        if (in_array($rowServer, $uniqueServers)) {
                            if (sizeof($uniqueMotds) < $limit) {
                                $motd = $row["motd"];

                                if (strlen(str_replace(" ", "", $motd)) > 0) {
                                    $motd = str_replace(array("\n", "\r"), " ", $motd);

                                    if (!in_array($motd, $uniqueMotds)) {
                                        array_push($uniqueMotds, $motd);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Separator
            $query = sql_query("SELECT license_id, file_id FROM accessTimeouts WHERE license_id IS NOT NULL OR file_id IS NOT NULL");

            if (isset($query->num_rows) && $query->num_rows > 0) {
                while ($row = $query->fetch_assoc()) {
                    $license_id = $row["license_id"];

                    if ($license_id != null) {
                        if ($license_id == $id) {
                            $timeouts += 1;
                        }
                    } else {
                        $file_id = $row["file_id"];

                        if ($file_id != null && in_array($file_id, $uniqueFiles)) {
                            $timeouts += 1;
                        }
                    }
                }
            }

            // Separator
            $query = sql_query("SELECT id FROM minecraft.punishedPlayers WHERE license_id = '$id';");
            $punishedPlayersCount = $query == null ? 0 : $query->num_rows;
            $query = sql_query("SELECT id FROM minecraft.disabledDetections WHERE license_id = '$id';");
            $disabledDetectionsCount = $query == null ? 0 : $query->num_rows;
            $query = sql_query("SELECT id FROM minecraft.automaticConfigurationChanges WHERE license_id = '$id';");
            $automaticConfigurationChangesCount = $query == null ? 0 : $query->num_rows;

             // Separator
            echo "<p><b>Details</b><br>";
            echo "Cloud ID: $id<br>";
            echo "Website ID: " . (!$hasWebsiteAccount ? "None" : $websiteAccount->id) . "<br>";
            echo "<a href='https://vagdedes.com/minecraft/cloud/verification/?id=$id&nonce=$platformArgument'>Verification URL</a><br>";

             // Separator
            echo "<br><b>State</b><br>";
            echo "Manually Suspended: $suspended<br>";
            echo "Automatically Suspended: $detected<br>";
            echo "Mass Check Exemption: $exemptedFromMassCheck<br>";

            echo "<br><b>Products</b><br>";
            $contents = private_file_get_contents1("https://vagdedes.com/minecraft/cloud/.tools/platformPurchases.php?id=$id");

            if ($contents !== false) {
                $json = json_decode($contents);

                if (isset($json->purchases)) {
                    $purchases = $json->purchases;

                    if (is_array($purchases) && sizeof($purchases) > 0) {
                        foreach ($purchases as $purchase) {
                            $purchaseDate = $purchase->custom->purchase_date;
                            $expirationDate = $purchase->custom->expiration_date;
                            echo $purchase->name . ": " . ($purchaseDate != null ? $purchaseDate : "None") . " " . ($expirationDate != null ? $expirationDate : "None") . "<br>";
                        }
                    } else {
                        echo "None<br>";
                    }
                } else {
                    echo "None<br>";
                }
            }

             // Separator
            echo "<br><b>Properties</b><br>";
            echo "Purchase Platform: " . ($hasPlatform ? $platform : "Unknown") . "<br>";
            echo "First Load Date: " . ($firstLoadDate != null ? $firstLoadDate : "Unknown") . "<br>";
            echo "Website Account Verification Date: " . ($hasVerifiedWebsiteAccount ? $websiteAccount->verification_date : "None") . "<br>";

             // Separator
            echo "<br><b>Connections</b><br>";
            echo "Sucessful Verifications: $successes<br>";
            echo "Failed Verifications: $failures<br>";
            echo "System Timeouts: $timeouts<br>";

             // Separator
            echo "<br><b>Features</b><br>";
            echo "Punished Players: $punishedPlayersCount<br>";
            echo "Disabled Detections: $disabledDetectionsCount<br>";
            echo "Automatic Configuration Changes: $automaticConfigurationChangesCount<br>";

             // Separator
            echo "<br><b>Limits</b><br>";
            $serverLimit = getServerLimit1($id);
            echo "Custom Server Limitation: $serverLimit<br>";

            // Separator
            echo "<p><b>Suspended Files</b><br>";
            $size = sizeof($suspendedFiles);

            if ($size == 0) {
                echo "None";
            } else {
                foreach ($suspendedFiles as $file) {
                    echo $file . "<br>";
                }
            }

            echo "<p><b>Servers</b><br>";
            $size = sizeof($uniqueServers);
            $desiredServerLimit = sizeof(getRecentlyUsedServers1($id));

            if ($size > 0) {
                foreach ($uniqueServers as $server) {
                    if (array_key_exists($server, $uniquePorts)) {
                        $ports = $uniquePorts[$server];
                        $servers = sizeof($ports);
                        echo $server . " (x$servers)";

                        if ($servers > 0) {
                            $portsString = "";

                            foreach ($ports as $port) {
                                $portsString .= $port . ", ";
                            }
                            echo " [" . substr($portsString, 0, -2) . "]";
                        }
                    } else {
                        echo $server . " (x0)";
                    }
                    echo "<br>";
                }
            } else {
                echo "None";
            }

            // Separator
            echo "<p><b>Motds</b><br>";

            if (sizeof($uniqueMotds) > 0) {
                foreach ($uniqueMotds as $motd) {
                    echo $motd . "<br>";
                }
            } else {
                echo "None";
            }

            // Separator
            $query = sql_query("SELECT version, plugin FROM minecraft.connectionCount WHERE license_id = '$id';");

            if (isset($query->num_rows) && $query->num_rows > 0) {
                while ($row = $query->fetch_assoc()) {
                    $fullVersion = $row["version"] . "(" . $row["plugin"] .")";

                    if (!in_array($fullVersion, $uniqueVersions)) {
                        array_push($uniqueVersions, $fullVersion);
                    }
                }
            }
            echo "<p><b>Versions Recently Used</b><br>";

            if (sizeof($uniqueVersions) > 0) {
                rsort($uniqueVersions);
                $versions = "";
                $latestVersion = null;

                foreach ($uniqueVersions as $version) {
                    if ($latestVersion == null) {
                        $versionSplit = explode("(", str_replace(")", "", $version));

                        if ($versionSplit[1] == "Spartan") {
                            $latestVersion = $versionSplit[0];
                        }
                    }
                    $versions .= $version . ", ";
                }
                echo substr($versions, 0, -2);

                // Separator

                if ($latestVersion != null) {
                    if (isset($_POST["FixSpeedFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Speed.minimum_limit_in_blocks", "0.6");
                    } else if (isset($_POST["FixExtremeSpeedFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Speed.minimum_limit_in_blocks", "1.0");
                    } else if (isset($_POST["FixIrregularMovementsFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "IrregularMovements.step_limit_in_blocks", "3.0");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "IrregularMovements.check_hopping", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "IrregularMovements.check_jumping", "false");
                    } else if (isset($_POST["FixHitReachFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "HitReach.horizontal_distance", "5.5");
                    } else if (isset($_POST["FixGhostHandFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "GhostHand.check_block_breaking", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "GhostHand.check_player_interactions", "false");
                    } else if (isset($_POST["FixCriticalsFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Criticals.check_damage", "false");
                    } else if (isset($_POST["FixExploitsFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Exploits.check_undetected_movement", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Exploits.check_head_position", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Exploits.check_chat_messages", "false");
                    } else if (isset($_POST["FixJesusFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Jesus.minimum_limit_in_blocks", "0.3");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Jesus.strict_swimming_algorithm", "false");
                    } else if (isset($_POST["FixExtremeJesusFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Jesus.minimum_limit_in_blocks", "0.5");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Jesus.strict_swimming_algorithm", "false");
                    } else if (isset($_POST["FixVelocityFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "Velocity.check_zero_distance", "false");
                    } else if (isset($_POST["FixNoFallFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "NoFall.check_ratio", "false");
                    } else if (isset($_POST["FixEntityMove(Boat)FalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "EntityMove.compatibility_protection", "true");
                    } else if (isset($_POST["FixKillAuraFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "KillAura.check_angle", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "KillAura.check_direction", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "KillAura.check_hit_box", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "KillAura.check_hit_distance", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "KillAura.check_fight_analysis", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "KillAura.aimbot_max_distance", "0.0");
                    } else if (isset($_POST["FixFastClicksFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "FastClicks.check_click_time", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "FastClicks.check_click_consistency", "false");
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "FastClicks.cps_limit", "20");
                    } else if (isset($_POST["FixMorePacketsFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "MorePackets.check_instant_movements", "false");
                    } else if (isset($_POST["FixFastBreakFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "FastBreak.check_delay", "false");
                     } else if (isset($_POST["FixNoSwingFalsePositives"])) {
                        manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "NoSwing.check_breaking", "false");
                     } else if (isset($_POST["FixFastPlaceFalsePositives"])) {
                         manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "FastPlace.check_fast", "false");
                     } else if (isset($_POST["FixBlockReachFalsePositives"])) {
                         manageAutomaticConfigurationChange1($id, $latestVersion, "checks", "BlockReach.overall_distance", "16.0");
                     } else if (isset($_POST["addConfigurationChange"])) {
                        $file = get_form_post("file");
                        $option = get_form_post("option");
                        $value = get_form_post("value");

                        if (strlen($file) == 0) {
                            $file = "checks";
                        }
                        if (strlen($value) == 0) {
                            $value = "false";
                        }
                        manageAutomaticConfigurationChange1($id, $latestVersion, $file, $option, $value);
                     }
                 }
            } else {
                echo "None";
            }

            echo "<p><b>Actions</b><br>";

            if ($hasWebsiteAccount && !$hasVerifiedWebsiteAccount) {
                echo "<form method='post' style='margin: 0; padding: 0;'>
                    <input type='submit' name='verifyWebsiteAccount' value='Verify Website Account' style='margin: 0; padding: 0;'>
                </form>";
            }
            if (!ownsExtraFunctionality2($spartan_syn_key1, $id)) {
                echo "<form method='post' style='margin: 0; padding: 0;'>
                    <input type='submit' name='createSynCustomer' value='Create Syn Customer' style='margin: 0; padding: 0;'>
                </form><p>";
            }

            if ($suspendedUser) {
                echo "<form method='post' style='margin: 0; padding: 0;'>
                                <input type='submit' name='unbanUser' value='Unban User' style='margin: 0; padding: 0;'>
                            </form>";
            }
            if (!$massCheckExemption) {
                echo "<form method='post' style='margin: 0; padding: 0;'>
                    <input type='submit' name='addMassExemption' value='Add Mass Exemption' style='margin: 0; padding: 0;'>
                </form><p>";
            }

            if ($desiredServerLimit > 1) {
                $desiredServerLimit = max($desiredServerLimit, 10);
                echo "<form method='post' style='margin: 0; padding: 0;'>
                    <input type='submit' name='updateServerLimit' value='Update Server Limit to $desiredServerLimit' style='margin: 0; padding: 0;'>
                </form>";
            }
            echo "<form method='post' style='margin: 0; padding: 0;'>
                <input type='number' name='limit' value='IP/Port/Server Limitation' style='margin: 0; padding: 0;'>
                <br>
                <input type='submit' name='updateServerLimit' value='Update Server Limit' style='margin: 0; padding: 0;'>
            </form><p>";

            foreach (array("Fix Speed False Positives",
                                    "Fix Extreme Speed False Positives",
                                    "Fix Jesus False Positives",
                                    "Fix Extreme Jesus False Positives",
                                    "Fix IrregularMovements False Positives",
                                    "Fix Criticals False Positives",
                                    "Fix HitReach False Positives",
                                    "Fix GhostHand False Positives",
                                    "Fix Exploits False Positives",
                                    "Fix Velocity False Positives",
                                    "Fix NoFall False Positives",
                                    "Fix EntityMove (Boat) False Positives",
                                    "Fix KillAura False Positives",
                                    "Fix FastClicks False Positives",
                                    "Fix MorePackets False Positives",
                                    "Fix FastBreak False Positives",
                                    "Fix NoSwing False Positives",
                                    "Fix FastPlace False Positives",
                                    "Fix Block Reach False Positives") as $key) {
                 $spacelessKey = str_replace(" ", "", $key);
                 echo "<form method='post' style='margin: 0; padding: 0;'>
                                <input type='submit' name='$spacelessKey' value='$key' style='margin: 0; padding: 0;'>
                            </form>";
            }
            echo "<form method='post' style='margin: 0; padding: 0;'>
                            <input type='text' name='file' placeholder='File Name' style='margin: 0; padding: 0;'>
                            <br>
                            <input type='text' name='option' placeholder='Custom Option' style='margin: 0; padding: 0;'>
                            <br>
                            <input type='text' name='value' placeholder='Option Value' style='margin: 0; padding: 0;'>
                            <br>
                            <input type='submit' name='addConfigurationChange' value='Add Configuration Change' style='margin: 0; padding: 0;'>
                        </form><p>";
        }
    }
}
