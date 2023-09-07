<?php

function send_email_by_plan($planID, $emailPointer, $details = null, $unsubscribe = true, $cooldown = null): int
{
    $currentDate = get_current_date();

    // Verify pointer
    if (!is_email($emailPointer)) {
        global $email_failed_executions_table;
        $code = 437892495;
        sql_insert($email_failed_executions_table, get_email_execution_insert_details(
            $planID,
            null,
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
            global $email_default_company_name;
            $details["defaultCompanyName"] = $email_default_company_name;
        }
        if (!array_key_exists("defaultEmailName", $details)) {
            global $email_default_email_name;
            $details["defaultEmailName"] = $email_default_email_name;
        }
    } else {
        global $email_default_company_name, $email_default_email_name;
        $details = array(
            "defaultDomainName" => get_domain(),
            "defaultCompanyName" => $email_default_company_name,
            "defaultEmailName" => $email_default_email_name
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
        $emailPointer,
        $details,
        $unsubscribe,
        $cooldown,
        "email"
    );

    // Necessary cache
    if (has_memory_cooldown($cacheKey, "1 second")) {
        return 985064734;
    }
    global $email_plans_table;

    // Find plan
    set_sql_cache("1 minute");
    $query = get_sql_query(
        $email_plans_table,
        array("id", "test", "redundant", "title", "comments", "contents", "default_cooldown"),
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
        global $email_failed_executions_table;
        $code = 342432524;
        sql_insert($email_failed_executions_table, get_email_execution_insert_details(
            $planID,
            null,
            null,
            null,
            $currentDate,
            $cooldown,
            $code
        ));
        return $code;
    }
    global $email_executions_table, $email_storage_table, $email_exemptions_table;
    $executed = array();
    $planObject = $query[0];
    $planID = $planObject->id;
    $isTest = $planObject->test !== null;

    // Load executions
    if (!$isTest && $planObject->redundant === null) {
        $query = get_sql_query(
            $email_executions_table,
            array("email_id", "cooldown_expiration_date"),
            array(
                array("plan_id", $planID),
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $rowID = $row->email_id;

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

    foreach (explode(",", $emailPointer) as $key => $individual) {
        $queryResults = get_sql_query(
            $email_storage_table,
            array("id", "test"),
            array(
                array("email_address", $individual),
            ),
            null,
            1
        );

        if (empty($queryResults)) {
            insert_new_email($emailPointer, $isTest);
            $queryResults = get_sql_query(
                $email_storage_table,
                array("id", "email_address"),
                array(
                    array("email_address", $individual),
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
                $object->email_address = $individual;
                $query[$key] = $object;
            }
        } else {
            $queryChild = get_sql_query(
                $email_exemptions_table,
                array(),
                array(
                    array("plan_id", $planID),
                    array("deletion_date", null),
                    array("email_id", $queryResults[0]->id)
                )
            );

            if (empty($queryChild)) {
                $object = new stdClass();
                $object->id = $queryResults[0]->id;
                $object->email_address = $individual;
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
    $title = $planObject->title;
    $contents = $planObject->contents;

    foreach ($details as $arrayKey => $arrayValue) {
        if (!empty($arrayKey)) {
            $arrayKey = "%%__" . $arrayKey . "__%%";
            $arrayValue = empty($arrayValue) ? "" : $arrayValue;
            $title = str_replace($arrayKey, $arrayValue, $title);
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

    // Prepare emails
    $total = array();
    $databaseInsertions = array();

    foreach ($query as $row) {
        $rowID = $row->id;

        if (!in_array($rowID, $executed)) {
            $executed[] = $rowID;
            $credential = $row->email_address;
            $total[$rowID] = $credential;

            if (!$isTest) {
                $databaseInsertions[$credential] = get_email_execution_insert_details(
                    $planID,
                    $rowID,
                    $title,
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

    // Load blacklisted emails
    global $email_blacklist_table, $email_failed_executions_table;
    $query = get_sql_query($email_blacklist_table,
        array("email_address", "ignore_case", "identification_method"),
        array(
            array("deletion_date", null)
        )
    );
    $hasBlacklisted = !empty($query);

    // Send emails
    foreach ($total as $credential) {
        if ($hasBlacklisted) {
            foreach ($query as $properties) {
                $credentialCopy = $credential;

                if ($properties->ignore_case) {
                    $credential = strtolower($credential);
                }
                switch (trim($properties->identification_method)) {
                    case "startsWith":
                        if (starts_with($credentialCopy, $properties->email_address)) {
                            continue 3;
                        }
                        break;
                    case "endsWith":
                        if (ends_with($credentialCopy, $properties->email_address)) {
                            continue 3;
                        }
                        break;
                    case "equals":
                        if ($credentialCopy == $properties->email_address) {
                            continue 3;
                        }
                        break;
                    case "contains":
                        if (strpos($credentialCopy, $properties->email_address) !== false) {
                            continue 3;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        $customContents = $contents;

        if ($unsubscribe) {
            $customContents .= "<p><a href='https://vagdedes.com/email/exempt/?token=" . get_user_exemption_token($planID, $rowID) . "'>Click to unsubscribe from this email</a>";
        }
        $execution = services_email($credential, null, $title, $customContents);

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($credential, $databaseInsertions)) {
                sql_insert($email_executions_table, $databaseInsertions[$credential]);
            }
        } else {
            $databaseInsertions[$credential]["error"] = $execution;
            sql_insert($email_failed_executions_table, $databaseInsertions[$credential]);
        }
    }
    return 1;
}

function get_user_exemption_token($planID, $emailID)
{
    global $email_user_exemption_keys_table, $email_exempt_token_length;
    $query = get_sql_query(
        $email_user_exemption_keys_table,
        array("id", "token"),
        array(
            array("plan_id", $planID),
            array("email_id", $emailID),
            array("deletion_date", null)
        ),
        null,
        1
    );

    // Create
    if (empty($query)) {
        $token = random_string($email_exempt_token_length);

        if (!sql_insert($email_user_exemption_keys_table,
            array(
                "plan_id" => $planID,
                "email_id" => $emailID,
                "token" => $token
            ))) {
            return "";
        }
        return $token;
    }

    // Found
    $token = $query[0]->token;

    if (strlen($token) === $email_exempt_token_length) {
        return $token;
    }

    // Resize
    $token = random_string($email_exempt_token_length);

    if (!sql_query("UPDATE $email_user_exemption_keys_table SET token = '$token' WHERE plan_id = '$planID' AND email_id = '$emailID';")) {
        return "";
    }
    return $token;
}

function insert_new_email($email, $test): bool
{
    global $email_storage_table;
    $array = get_sql_query(
        $email_storage_table,
        array("id"),
        array(
            array("email_address", $email),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                $email_storage_table,
                array(
                    "email_address" => $email,
                    "test" => $test,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function get_email_execution_insert_details($planID, $rowID, $title, $contents, $currentDate, $cooldown, $error = null): array
{
    $array = array(
        "plan_id" => $planID,
        "email_id" => $rowID,
        "title" => $title,
        "contents" => $contents,
        "creation_date" => $currentDate,
        "cooldown_expiration_date" => $cooldown
    );

    if ($error !== null) {
        $array["error"] = $error;
    }
    return $array;
}
