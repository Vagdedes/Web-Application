<?php

function send_email_by_plan(int|string|float $planID, string $emailPointer,
                            ?array           $details = null,
                            bool             $unsubscribe = true,
                            int|string|null  $cooldown = null): int
{
    $currentDate = get_current_date();

    // Verify pointer
    if (!is_email($emailPointer)) {
        $code = 437892495;
        sql_insert(EmailVariables::FAILED_EXECUTIONS_TABLE, get_email_execution_insert_details(
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
    $domain = get_domain();
    $informationPlaceholder = new InformationPlaceholder();

    if (is_array($details)) {
        global $email_default_email_name;
        $informationPlaceholder->setAll($details);
        $informationPlaceholder->addAll(array(
            "defaultDomainName" => $domain,
            "defaultCompanyName" => EmailVariables::DEFAULT_COMPANY_NAME,
            "defaultEmailName" => $email_default_email_name
        ));
    } else {
        global $email_default_email_name;
        $informationPlaceholder->setAll(array(
            "defaultDomainName" => $domain,
            "defaultCompanyName" => EmailVariables::DEFAULT_COMPANY_NAME,
            "defaultEmailName" => $email_default_email_name
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
        $emailPointer,
        $informationPlaceholder->getReplacements(),
        $unsubscribe,
        $cooldown,
        "email"
    );

    // Necessary cache
    if (has_memory_cooldown($cacheKey, "1 second")) {
        return 985064734;
    }

    // Find plan
    $query = get_sql_query(
        EmailVariables::PLANS_TABLE,
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
        $code = 342432524;
        sql_insert(EmailVariables::FAILED_EXECUTIONS_TABLE, get_email_execution_insert_details(
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
    $executed = array();
    $planObject = $query[0];
    $planID = $planObject->id;
    $isTest = $planObject->test !== null;

    // Load executions
    if (!$isTest && $planObject->redundant === null) {
        $query = get_sql_query(
            EmailVariables::EXECUTIONS_TABLE,
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
            EmailVariables::STORAGE_TABLE,
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
                EmailVariables::STORAGE_TABLE,
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
                EmailVariables::EXEMPTIONS_TABLE,
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
    $title = $informationPlaceholder->replace($planObject->title);
    $contents = $informationPlaceholder->replace($planObject->contents);

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
    $query = get_sql_query(
        EmailVariables::BLACKLIST_TABLE,
        array("email_address", "ignore_case", "identification_method"),
        array(
            array("deletion_date", null)
        )
    );
    $hasBlacklisted = !empty($query);

    // Send emails
    foreach ($total as $credential) {
        $originalCredential = $credential;

        if ($hasBlacklisted) {
            foreach ($query as $properties) {
                $credentialCopy = $credential;

                if ($properties->ignore_case) {
                    $credential = strtolower($credential);
                }
                switch (trim($properties->identification_method)) {
                    case "startsWith":
                        if (str_starts_with($credentialCopy, $properties->email_address)) {
                            continue 3;
                        }
                        break;
                    case "endsWith":
                        if (str_ends_with($credentialCopy, $properties->email_address)) {
                            continue 3;
                        }
                        break;
                    case "equals":
                        if ($credentialCopy == $properties->email_address) {
                            continue 3;
                        }
                        break;
                    case "contains":
                        if (str_contains($credentialCopy, $properties->email_address)) {
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
            $customContents .= "<p><a href='https://" . $domain . "/email/exempt/?token=" . get_user_exemption_token($planID, $rowID) . "'>Click to unsubscribe from this email</a>";
        }
        $execution = services_email($credential, null, $title, $customContents);

        if ($execution === true) {
            if ($hasCooldown) {
                has_memory_cooldown($cacheKey, $cooldown, true, true);
            }
            if (array_key_exists($originalCredential, $databaseInsertions)) {
                sql_insert(EmailVariables::EXECUTIONS_TABLE, $databaseInsertions[$originalCredential]);
            }
        } else {
            $databaseInsertions[$originalCredential]["error"] = $execution;
            sql_insert(EmailVariables::FAILED_EXECUTIONS_TABLE, $databaseInsertions[$originalCredential]);
        }
    }
    return 1;
}

function get_user_exemption_token(int|string|float $planID, int|string $emailID)
{
    $query = get_sql_query(
        EmailVariables::USER_EXEMPTION_KEYS_TABLE,
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
        $token = random_string(EmailVariables::EXEMPT_TOKEN_LENGTH);

        if (!sql_insert(
            EmailVariables::USER_EXEMPTION_KEYS_TABLE,
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

    if (strlen($token) === EmailVariables::EXEMPT_TOKEN_LENGTH) {
        return $token;
    }

    // Resize
    $token = random_string(EmailVariables::EXEMPT_TOKEN_LENGTH);

    if (!set_sql_query(
        EmailVariables::USER_EXEMPTION_KEYS_TABLE,
        array(
            "token" => $token
        ),
        array(
            array("plan_id", $planID),
            array("email_id", $emailID)
        ),
        null,
        1
    )) {
        return "";
    }
    return $token;
}

function insert_new_email(string $email, bool $test): bool
{
    $array = get_sql_query(
        EmailVariables::STORAGE_TABLE,
        array("id"),
        array(
            array("email_address", $email),
        ),
        null,
        1
    );

    if (empty($array)) {
        return sql_insert(
                EmailVariables::STORAGE_TABLE,
                array(
                    "email_address" => $email,
                    "test" => $test,
                    "creation_date" => get_current_date()
                )
            ) == true;
    }
    return false;
}

function get_email_execution_insert_details(int|string|float $planID,
                                            int|string|null  $rowID, $title, ?string $contents,
                                            ?string          $currentDate, ?string $cooldown,
                                            mixed            $error = null): array
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
