<?php

function send_phone_message_by_plan($planID, $phonePointer, $details = null, $cooldown = null): int
{
    $currentDate = get_current_date();

    // Verify pointer
    if (!is_phone_number($phonePointer)) {
        global $phone_failed_executions_table;
        $code = 437892495;
        sql_insert($phone_failed_executions_table, get_phone_execution_insert_details(
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
            global $phone_default_company_name;
            $details["defaultCompanyName"] = $phone_default_company_name;
        }
        if (!array_key_exists("defaultEmailName", $details)) {
            global $phone_default_email_name;
            $details["defaultEmailName"] = $phone_default_email_name;
        }
    } else {
        global $phone_default_company_name, $phone_default_email_name;
        $details = array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => $phone_default_company_name,
            "defaultEmailName" => $phone_default_email_name
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
        $phonePointer,
        $details,
        $cooldown,
        "phone"
    );

    // Necessary cache
    if (has_memory_cooldown($cacheKey, "1 second")) {
        return 985064734;
    }
    global $phone_plans_table;

    // Find plan
    $numericPLan = is_numeric($planID);
    set_sql_cache("1 minute");
    $query = get_sql_query(
        $phone_plans_table,
        array("id", "test", "redundant", "comments", "contents", "default_cooldown"),
        array(
            $numericPLan ? array("id", $planID) : "",
            $numericPLan ? "" : array("name", $planID),
            array("deletion_date", null),
            array("launch_date", "IS NOT", null)
        ),
        null,
        1
    );

    if (empty($query)) {
        global $phone_failed_executions_table;
        $code = 342432524;
        sql_insert($phone_failed_executions_table, get_phone_execution_insert_details(
            $planID,
            null,
            null,
            $currentDate,
            $cooldown,
            $code
        ));
        return $code;
    }
    global $phone_executions_table, $phone_storage_table, $phone_exemptions_table;
    $executed = array();
    $planObject = $query[0];
    $planID = $planObject->id;
    $isTest = $planObject->test !== null;

    // Load executions
    if (!$isTest && $planObject->redundant === null) {
        $query = get_sql_query(
            $phone_executions_table,
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
            $phone_storage_table,
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
                $phone_storage_table,
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
                $phone_exemptions_table,
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
    $contents = $planObject->contents;

    foreach ($details as $arrayKey => $arrayValue) {
        if (!empty($arrayKey)) {
            $arrayKey = "%%__" . $arrayKey . "__%%";
            $arrayValue = empty($arrayValue) ? "" : $arrayValue;
            $contents = str_replace($arrayKey, $arrayValue, $contents);
        }
    }

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
    global $phone_blacklist_table, $phone_failed_executions_table;
    $query = get_sql_query($phone_blacklist_table,
        array("phone_number", "contains"),
        array(
            array("deletion_date", null)
        )
    );
    $blacklisted = array();
    $hasBlacklisted = false;

    if (!empty($query)) {
        $hasBlacklisted = true;

        foreach ($query as $row) {
            $credential = $row->phone_number;
            $blacklisted[$credential] = $row;
        }
    }

    // Send to phone numbers
    foreach ($total as $credential) {
        if ($hasBlacklisted) {
            $execute = true;

            foreach ($blacklisted as $credentialChild => $properties) {
                if ($properties->contains !== null ?
                    (strpos($credentialChild, $credential) !== false) :
                    ($credential === $credentialChild)) {
                    $execute = false;
                    break;
                }
            }

            if (!$execute) {
                continue;
            }
        }
        $execution = send_phone_message($credential, $contents);

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($credential, $databaseInsertions)) {
                sql_insert($phone_executions_table, $databaseInsertions[$credential]);
            }
        } else {
            $databaseInsertions[$credential]["error"] = $execution;
            sql_insert($phone_failed_executions_table, $databaseInsertions[$credential]);
        }
    }
    return 1;
}

function insert_new_phone_number($number, $test): bool
{
    global $phone_storage_table;
    $array = get_sql_query(
        $phone_storage_table,
        array("id"),
        array(
            array("phone_number", $number),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                $phone_storage_table,
                array(
                    "phone_number" => $number,
                    "test" => $test,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function get_phone_execution_insert_details($planID, $rowID, $contents, $currentDate, $cooldown, $error = null): array
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
