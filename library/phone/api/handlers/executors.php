<?php

function send_phone_message_by_plan(int|string|float $planID, int|string $phonePointer,
                                    ?array           $details = null,
                                    string|int|null  $cooldown = null): int
{
    $currentDate = get_current_date();

    // Verify pointer
    if (!is_phone_number($phonePointer)) {
        $code = 437892495;
        sql_insert(PhoneVariables::FAILED_EXECUTIONS_TABLE, get_phone_execution_insert_details(
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
    $informationPlaceholder = new InformationPlaceholder();

    if (is_array($details)) {
        global $phone_default_email_name;
        $informationPlaceholder->setAll($details);
        $informationPlaceholder->addAll(array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => PhoneVariables::DEFAULT_COMPANY_NAME,
            "defaultEmailName" => $phone_default_email_name
        ));
    } else {
        global $phone_default_email_name;
        $informationPlaceholder->setAll(array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => PhoneVariables::DEFAULT_COMPANY_NAME,
            "defaultEmailName" => $phone_default_email_name
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
        $phonePointer,
        $informationPlaceholder->getReplacements(),
        $cooldown,
        "phone"
    );

    // Necessary cache
    if (has_memory_cooldown($cacheKey, "1 second")) {
        return 985064734;
    }

    // Find plan
    $query = get_sql_query(
        PhoneVariables::PLANS_TABLE,
        array("id", "test", "redundant", "comments", "contents", "default_cooldown"),
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
        sql_insert(PhoneVariables::FAILED_EXECUTIONS_TABLE, get_phone_execution_insert_details(
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
    if (!$isTest && $planObject->redundant === null) {
        $query = get_sql_query(
            PhoneVariables::EXECUTIONS_TABLE,
            array("phone_id", "cooldown_expiration_date"),
            array(
                array("plan_id", $planID),
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $rowID = $row->phone_id;

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

    foreach (explode(",", $phonePointer) as $key => $individual) {
        $queryResults = get_sql_query(
            PhoneVariables::STORAGE_TABLE,
            array("id", "test"),
            array(
                array("phone_number", $individual),
            ),
            null,
            1
        );

        if (empty($queryResults)) {
            insert_new_phone_number($phonePointer, $isTest);
            $queryResults = get_sql_query(
                PhoneVariables::STORAGE_TABLE,
                array("id", "phone_number"),
                array(
                    array("phone_number", $individual),
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
                $object->phone_number = $individual;
                $query[$key] = $object;
            }
        } else {
            $queryChild = get_sql_query(
                PhoneVariables::EXEMPTIONS_TABLE,
                array(),
                array(
                    array("plan_id", $planID),
                    array("deletion_date", null),
                    array("phone_id", $queryResults[0]->id)
                )
            );

            if (empty($queryChild)) {
                $object = new stdClass();
                $object->id = $queryResults[0]->id;
                $object->phone_number = $individual;
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
    $contents = $informationPlaceholder->replace($planObject->contents);

    // Adjust for default cooldown
    if (!$hasCooldown) {
        $planDefaultCooldown = $planObject->default_cooldown;

        if ($planDefaultCooldown !== null) {
            $cooldown = get_future_date($planDefaultCooldown);
            $hasCooldown = true;
        }
    }

    // Prepare phone numbers
    $total = array();
    $databaseInsertions = array();

    foreach ($query as $row) {
        $rowID = $row->id;

        if (!in_array($rowID, $executed)) {
            $executed[] = $rowID;
            $credential = $row->phone_number;
            $total[$rowID] = $credential;

            if (!$isTest) {
                $databaseInsertions[$credential] = get_phone_execution_insert_details(
                    $planID,
                    $rowID,
                    $contents,
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

    // Load blacklisted phone numbers
    $query = get_sql_query(
        PhoneVariables::BLACKLIST_TABLE,
        array("phone_number", "identification_method"),
        array(
            array("deletion_date", null)
        )
    );
    $hasBlacklisted = !empty($query);

    // Send to phone numbers
    foreach ($total as $credential) {
        if ($hasBlacklisted) {
            foreach ($query as $properties) {
                switch (trim($properties->identification_method)) {
                    case "startsWith":
                        if (str_starts_with($credential, $properties->phone_number)) {
                            continue 3;
                        }
                        break;
                    case "endsWith":
                        if (str_ends_with($credential, $properties->phone_number)) {
                            continue 3;
                        }
                        break;
                    case "equals":
                        if ($credential == $properties->phone_number) {
                            continue 3;
                        }
                        break;
                    case "contains":
                        if (str_contains($credential, $properties->phone_number)) {
                            continue 3;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        $execution = send_phone_message($credential, $contents);

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($credential, $databaseInsertions)) {
                sql_insert(PhoneVariables::EXECUTIONS_TABLE, $databaseInsertions[$credential]);
            }
        } else {
            $databaseInsertions[$credential]["error"] = $execution;
            sql_insert(PhoneVariables::FAILED_EXECUTIONS_TABLE, $databaseInsertions[$credential]);
        }
    }
    return 1;
}

function insert_new_phone_number(int|string $number, bool $test): bool
{
    $array = get_sql_query(
        PhoneVariables::STORAGE_TABLE,
        array("id"),
        array(
            array("phone_number", $number),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                PhoneVariables::STORAGE_TABLE,
                array(
                    "phone_number" => $number,
                    "test" => $test,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function get_phone_execution_insert_details(int|string|float $planID,
                                            int|string|null  $rowID, ?string $contents,
                                            ?string          $currentDate, ?string $cooldown,
                                            mixed            $error = null): array
{
    $array = array(
        "plan_id" => $planID,
        "phone_id" => $rowID,
        "contents" => $contents,
        "creation_date" => $currentDate,
        "cooldown_expiration_date" => $cooldown
    );

    if ($error !== null) {
        $array["error"] = $error;
    }
    return $array;
}
