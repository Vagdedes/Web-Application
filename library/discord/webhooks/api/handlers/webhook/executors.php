<?php

function send_discord_webhook_by_plan($planID, $webhookPointer, $details = null, $cooldown = null): int
{
    $currentDate = get_current_date();

    // Verify pointer
    if (!is_url($webhookPointer)) {
        global $discord_webhook_failed_executions_table;
        $code = 437892495;
        sql_insert($discord_webhook_failed_executions_table, get_discord_webhook_execution_insert_details(
            $planID,
            null,
            null,
            $currentDate,
            $cooldown,
            $code
        ));
        return $code;
    }

    // Verify details
    if (is_array($details)) {
        if (!array_key_exists("defaultDomainName", $details)) {
            $details["defaultDomainName"] = get_domain();
        }
        if (!array_key_exists("defaultCompanyName", $details)) {
            global $discord_webhook_default_company_name;
            $details["defaultCompanyName"] = $discord_webhook_default_company_name;
        }
        if (!array_key_exists("defaultEmailName", $details)) {
            global $discord_webhook_default_email_name;
            $details["defaultEmailName"] = $discord_webhook_default_email_name;
        }
    } else {
        global $discord_webhook_default_company_name, $discord_webhook_default_email_name;
        $details = array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => $discord_webhook_default_company_name,
            "defaultEmailName" => $discord_webhook_default_email_name
        );
    }

    // Verify cooldown
    if ($cooldown !== null) {
        $cooldown = get_future_date($cooldown);
        $hasCooldown = true;
    } else {
        $hasCooldown = false;
    }

    // Create cache key
    $cacheKey = array(
        $planID,
        $webhookPointer,
        $details,
        $cooldown,
        "discord-webhook"
    );

    // Necessary cache
    if (has_memory_cooldown($cacheKey, "1 second")) {
        return 985064734;
    }
    global $discord_webhook_plans_table;

    // Find plan
    set_sql_cache();
    $query = get_sql_query(
        $discord_webhook_plans_table,
        null,
        array(
            array("name", $planID),
            array("deletion_date", null),
            null,
            array("expiration_date", "IS", null, 0),
            array("expiration_date", ">", $currentDate),
            null
        ),
        null,
        1
    );

    if (empty($query)) {
        global $discord_webhook_failed_executions_table;
        $code = 342432524;
        sql_insert($discord_webhook_failed_executions_table, get_discord_webhook_execution_insert_details(
            $planID,
            null,
            null,
            $currentDate,
            $cooldown,
            $code
        ));
        return $code;
    }
    global $discord_webhook_executions_table, $discord_webhook_storage_table, $discord_webhook_exemptions_table;
    $executed = array();
    $planObject = $query[0];
    $planID = $planObject->id;
    $isTest = $planObject->test !== null;

    // Load executions
    if (!$isTest) {
        $query = get_sql_query(
            $discord_webhook_executions_table,
            array("webhook_id", "cooldown_expiration_date"),
            array(
                array("plan_id", $planID),
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $rowID = $row->webhook_id;

                if (!in_array($rowID, $executed)) {
                    $cooldownExpirationDate = $row->cooldown_expiration_date;

                    if ($cooldownExpirationDate === null
                        || $currentDate < $cooldownExpirationDate) {
                        $executed[] = $rowID;
                    }
                }
            }
        }
    }

    // Check pointers
    $query = array();

    foreach (explode(",", $webhookPointer) as $key => $individual) {
        $queryResults = get_sql_query(
            $discord_webhook_storage_table,
            array("id", "test"),
            array(
                array("webhook_url", $individual),
            ),
            null,
            1
        );

        if (empty($queryResults)) {
            insert_new_webhook_url($webhookPointer, $isTest);
            $queryResults = get_sql_query(
                $discord_webhook_storage_table,
                array("id", "webhook_url"),
                array(
                    array("webhook_url", $individual),
                ),
                null,
                1
            );

            if (!empty($queryResults)) {
                $query[$key] = $queryResults[0];
            }
        } else if ($isTest) {
            $row = $queryResults[0];

            if ($row->test === null) {
                unset($query[$key]);

                if (empty($query)) {
                    return 892240212;
                }
            } else {
                $object = new stdClass();
                $object->id = $row->id;
                $object->webhook_url = $individual;
                $query[$key] = $object;
            }
        } else {
            $queryChild = get_sql_query(
                $discord_webhook_exemptions_table,
                array(),
                array(
                    array("plan_id", $planID),
                    array("deletion_date", null),
                    array("webhook_id", $queryResults[0]->id)
                )
            );

            if (empty($queryChild)) {
                $object = new stdClass();
                $object->id = $queryResults[0]->id;
                $object->webhook_url = $individual;
                $query[$key] = $object;
            } else {
                unset($query[$key]);

                if (empty($query)) {
                    return 948892075;
                }
            }
        }
    }

    // Prepare details
    $webhookNames = explode("%%", $planObject->webhook_names);
    $webhookValues = explode("%%", $planObject->webhook_values);

    if (sizeof($webhookNames) === sizeof($webhookValues)) {
        unset($planObject->webhook_names);
        unset($planObject->webhook_values);
        $planObject->fields = array();

        foreach ($webhookNames as $key => $value) {
            $name = $value;
            $value = $webhookValues[$key];

            foreach ($details as $arrayKey => $arrayValue) {
                if (!empty($arrayKey)) {
                    $arrayKey = "%%__" . $arrayKey . "__%%";
                    $arrayValue = empty($arrayValue) ? "" : $arrayValue;
                    $name = str_replace($arrayKey, $arrayValue, $name);
                    $value = str_replace($arrayKey, $arrayValue, $value);
                }
            }
            $planObject->fields[] = array(
                "name" => $name,
                "value" => $value,
                "inline" => false
            );
        }
        foreach ($details as $arrayKey => $arrayValue) {
            if (!empty($arrayKey)) {
                $arrayKey = "%%__" . $arrayKey . "__%%";
                $arrayValue = empty($arrayValue) ? "" : $arrayValue;

                if ($planObject->color !== null) {
                    $planObject->color = str_replace($arrayKey, $arrayValue, $planObject->color);
                }
                if ($planObject->avatar_image !== null) {
                    $planObject->avatar_image = str_replace($arrayKey, $arrayValue, $planObject->avatar_image);
                }
                if ($planObject->icon_image !== null) {
                    $planObject->icon_image = str_replace($arrayKey, $arrayValue, $planObject->icon_image);
                }
                if ($planObject->redirect_url !== null) {
                    $planObject->redirect_url = str_replace($arrayKey, $arrayValue, $planObject->redirect_url);
                }
                if ($planObject->title !== null) {
                    $planObject->title = str_replace($arrayKey, $arrayValue, $planObject->title);
                }
                if ($planObject->footer !== null) {
                    $planObject->footer = str_replace($arrayKey, $arrayValue, $planObject->footer);
                }
                if ($planObject->user !== null) {
                    $planObject->user = str_replace($arrayKey, $arrayValue, $planObject->user);
                }
                if ($planObject->information !== null) {
                    $planObject->information = str_replace($arrayKey, $arrayValue, $planObject->information);
                }
                if ($planObject->user !== null) {
                    $planObject->user = str_replace($arrayKey, $arrayValue, $planObject->user);
                }
            }
        }
    } else {
        global $discord_webhook_failed_executions_table;
        $code = 398054234;
        sql_insert($discord_webhook_failed_executions_table, get_discord_webhook_execution_insert_details(
            $planID,
            null,
            null,
            $currentDate,
            $cooldown,
            $code
        ));
        return $code;
    }

    // Adjust for default cooldown
    if (!$hasCooldown) {
        $planDefaultCooldown = $planObject->default_cooldown;

        if ($planDefaultCooldown !== null) {
            $cooldown = get_future_date($planDefaultCooldown);
            $hasCooldown = true;
        }
    }

    // Prepare webhook URLs
    $total = array();
    $databaseInsertions = array();

    foreach ($query as $row) {
        $rowID = $row->id;

        if (!in_array($rowID, $executed)) {
            $executed[] = $rowID;
            $credential = $row->webhook_url;
            $total[$rowID] = $credential;

            if (!$isTest) {
                $databaseInsertions[$credential] = get_discord_webhook_execution_insert_details(
                    $planID,
                    $rowID,
                    $planObject,
                    $currentDate,
                    $cooldown
                );
            }

            if (sizeof($total) === 500) {
                break;
            }
        }
    }

    if (empty($total)) {
        return 837920421;
    }

    // Load blacklisted webhook URLs
    global $discord_webhook_blacklist_table, $discord_webhook_failed_executions_table;
    $query = get_sql_query($discord_webhook_blacklist_table,
        array("webhook_url", "identification_method"),
        array(
            array("deletion_date", null)
        )
    );
    $hasBlacklisted = !empty($query);

    // Send to webhook URLs
    foreach ($total as $credential) {
        $credential = strtolower($credential);

        if ($hasBlacklisted) {
            foreach ($query as $properties) {
                switch (trim($properties->identification_method)) {
                    case "startsWith":
                        if (starts_with($credential, $properties->webhook_url)) {
                            continue 3;
                        }
                        break;
                    case "endsWith":
                        if (ends_with($credential, $properties->webhook_url)) {
                            continue 3;
                        }
                        break;
                    case "equals":
                        if ($credential == $properties->webhook_url) {
                            continue 3;
                        }
                        break;
                    case "contains":
                        if (strpos($credential, $properties->webhook_url) !== false) {
                            continue 3;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        $execution = send_discord_webhook(
            $webhookPointer,
            $planObject->color, null, null,
            $planObject->avatar_image,
            $planObject->icon_image,
            $planObject->redirect_url,
            $planObject->title,
            $planObject->footer,
            $planObject->fields,
            $planObject->user,
            $planObject->information
        );

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($credential, $databaseInsertions)) {
                sql_insert($discord_webhook_executions_table, $databaseInsertions[$credential]);
            }
        } else {
            $databaseInsertions[$credential]["error"] = $execution;
            sql_insert($discord_webhook_failed_executions_table, $databaseInsertions[$credential]);
        }
    }
    return 1;
}

function insert_new_webhook_url($number, $test): bool
{
    global $discord_webhook_storage_table;
    $array = get_sql_query(
        $discord_webhook_storage_table,
        array("id"),
        array(
            array("webhook_url", $number),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                $discord_webhook_storage_table,
                array(
                    "webhook_url" => $number,
                    "test" => $test,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function get_discord_webhook_execution_insert_details($planID, $rowID, $object, $currentDate, $cooldown, $error = null): array
{
    $array = array(
        "plan_id" => $planID,
        "webhook_id" => $rowID,
        "object" => $object !== null ? json_encode($object) : null,
        "creation_date" => $currentDate,
        "cooldown_expiration_date" => $cooldown
    );

    if ($error !== null) {
        $array["error"] = $error;
    }
    return $array;
}
