<?php

function send_discord_webhook_by_plan(int|string|float $planID, string $webhookPointer,
                                      ?array           $details = null,
                                      string|int|null  $cooldown = null): int|string
{
    $currentDate = get_current_date();

    // Verify pointer
    if (!is_url($webhookPointer)) {
        $code = 437892495;
        sql_insert(DiscordWebhookVariables::WEBHOOK_FAILED_EXECUTIONS_TABLE, get_discord_webhook_execution_insert_details(
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
        global $discord_webhook_default_email_name;
        $informationPlaceholder->setAll($details);
        $informationPlaceholder->addAll(array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => DiscordWebhookVariables::DEFAULT_COMPANY_NAME,
            "defaultEmailName" => $discord_webhook_default_email_name
        ));
    } else {
        global $discord_webhook_default_email_name;
        $informationPlaceholder->setAll(array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => DiscordWebhookVariables::DEFAULT_COMPANY_NAME,
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

    // Find plan
    $query = get_sql_query(
        DiscordWebhookVariables::WEBHOOK_PLANS_TABLE,
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
        $code = 342432524;
        sql_insert(DiscordWebhookVariables::WEBHOOK_FAILED_EXECUTIONS_TABLE, get_discord_webhook_execution_insert_details(
            $planID,
            null,
            null,
            $currentDate,
            $cooldown,
            $code
        ));
        return $code;
    }
    $executed = array();
    $planObject = $query[0];
    $planID = $planObject->id;
    $isTest = $planObject->test !== null;

    // Load executions
    if (!$isTest) {
        $query = get_sql_query(
            DiscordWebhookVariables::WEBHOOK_EXECUTIONS_TABLE,
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
            DiscordWebhookVariables::WEBHOOK_PLANS_TABLE,
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
                DiscordWebhookVariables::WEBHOOK_STORAGE_TABLE,
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
                DiscordWebhookVariables::WEBHOOK_EXEMPTIONS_TABLE,
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
        $planObject->author_name = $informationPlaceholder->replace($planObject->author_name);
        $planObject->author_url = $informationPlaceholder->replace($planObject->author_url);
        $planObject->author_icon_url = $informationPlaceholder->replace($planObject->author_icon_url);
        $planObject->footer_image = $informationPlaceholder->replace($planObject->footer_image);
        $planObject->title_url = $informationPlaceholder->replace($planObject->title_url);
        $planObject->title = $informationPlaceholder->replace($planObject->title);
        $planObject->description = $informationPlaceholder->replace($planObject->description);
        $planObject->footer = $informationPlaceholder->replace($planObject->footer);
        $planObject->information = $informationPlaceholder->replace($planObject->information);
    } else {
        $code = 398054234;
        sql_insert(DiscordWebhookVariables::WEBHOOK_FAILED_EXECUTIONS_TABLE, get_discord_webhook_execution_insert_details(
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
    $query = get_sql_query(DiscordWebhookVariables::WEBHOOK_BLACKLIST_TABLE,
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
            $planObject->avatar_image,
            $planObject->color,
            $planObject->author_name,
            $planObject->author_url,
            $planObject->author_icon_url,
            $planObject->title,
            $planObject->title_url,
            $planObject->description,
            $planObject->footer,
            $planObject->footer_image,
            $planObject->fields,
            empty($planObject->information) ? "" : $planObject->information
        );

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($originalCredential, $databaseInsertions)) {
                sql_insert(DiscordWebhookVariables::WEBHOOK_EXECUTIONS_TABLE, $databaseInsertions[$originalCredential]);
            }
        } else {
            $databaseInsertions[$originalCredential]["error"] = $execution;
            sql_insert(DiscordWebhookVariables::WEBHOOK_FAILED_EXECUTIONS_TABLE, $databaseInsertions[$originalCredential]);
            return $execution;
        }
    }
    return 1;
}

function insert_new_webhook_url(string $webhookPointer, bool $test): bool
{
    $array = get_sql_query(
        DiscordWebhookVariables::WEBHOOK_STORAGE_TABLE,
        array("id"),
        array(
            array("webhook_url", $webhookPointer),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                DiscordWebhookVariables::WEBHOOK_STORAGE_TABLE,
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
