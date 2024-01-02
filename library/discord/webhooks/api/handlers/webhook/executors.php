<?php

function send_discord_webhook_by_plan(int|string|float $planID, string $webhookPointer,
                                      ?array           $details = null,
                                      string|int|null  $cooldown = null): int
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
    $informationPlaceholder = new InformationPlaceholder("___", null, null, "___");

    // Verify details
    if (is_array($details)) {
        global $discord_webhook_default_company_name, $discord_webhook_default_email_name;
        $informationPlaceholder->setAll($details);
        $informationPlaceholder->addAll(array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => $discord_webhook_default_company_name,
            "defaultEmailName" => $discord_webhook_default_email_name
        ));
    } else {
        global $discord_webhook_default_company_name, $discord_webhook_default_email_name;
        $informationPlaceholder->setAll(array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => $discord_webhook_default_company_name,
            "defaultEmailName" => $discord_webhook_default_email_name
        ));
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
        $informationPlaceholder->getReplacements(),
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

                    if ($cooldownExpirationDate !== null
                        && $cooldownExpirationDate > $currentDate) {
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
            $planObject->fields[] = array(
                "name" => $informationPlaceholder->replace($value),
                "value" => $informationPlaceholder->replace($webhookValues[$key]),
                "inline" => false
            );
        }
        $planObject->color = $informationPlaceholder->replace($planObject->color);
        $planObject->avatar_image = $informationPlaceholder->replace($planObject->avatar_image);
        $planObject->icon_image = $informationPlaceholder->replace($planObject->icon_image);
        $planObject->redirect_url = $informationPlaceholder->replace($planObject->redirect_url);
        $planObject->title = $informationPlaceholder->replace($planObject->title);
        $planObject->footer = $informationPlaceholder->replace($planObject->footer);
        $planObject->information = $informationPlaceholder->replace($planObject->information);
        $planObject->user = $informationPlaceholder->replace($planObject->user);
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
        $originalCredential = $credential;
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
                        if (str_contains($credential, $properties->webhook_url)) {
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
            empty($planObject->information) ? "" : $planObject->information
        );

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($originalCredential, $databaseInsertions)) {
                sql_insert($discord_webhook_executions_table, $databaseInsertions[$originalCredential]);
            }
        } else {
            $databaseInsertions[$originalCredential]["error"] = $execution;
            sql_insert($discord_webhook_failed_executions_table, $databaseInsertions[$originalCredential]);
        }
    }
    return 1;
}

function insert_new_webhook_url(string $webhookPointer, bool $test): bool
{
    global $discord_webhook_storage_table;
    $array = get_sql_query(
        $discord_webhook_storage_table,
        array("id"),
        array(
            array("webhook_url", $webhookPointer),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                $discord_webhook_storage_table,
                array(
                    "webhook_url" => $webhookPointer,
                    "test" => $test,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function get_discord_webhook_execution_insert_details(int|string|float $planID,
                                                      int|string|null  $rowID, mixed $object,
                                                      ?string          $currentDate, ?string $cooldown,
                                                      mixed            $error = null): array
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
