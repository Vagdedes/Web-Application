<?php
require_once '/var/www/.structure/library/memory/init.php';

function getLocalVariable($key, $futureTime = null, $value = "")
{
    $value = manipulate_memory_key($value);

    if ($value !== null) {
        global $localVariablesTable;
        $key = manipulate_memory_key($key, $localVariablesTable);

        if ($key !== null) {
            $query = sql_query("SELECT value_pair, expiration_time FROM " . $localVariablesTable . " WHERE id = '$key' LIMIT 1;");

            if ($query != null && $query->num_rows === 1) {
                if ($futureTime !== null) {
                    $row = $query->fetch_assoc();
                    $expirationTime = $row["expiration_time"];

                    if ($expirationTime !== null && $expirationTime >= time()) {
                        $pair = $row["value_pair"];
                        return $pair === null ? "" : $pair;
                    }
                    $futureTime = manipulate_memory_date($futureTime, $localVariablesTable);

                    if ($futureTime !== false) {
                        sql_query("UPDATE " . $localVariablesTable . " SET expiration_time = '" . $futureTime . "' WHERE id = '" . $key . "';");
                        return null; // Return null when expired
                    }
                } else {
                    sql_query("UPDATE " . $localVariablesTable . " SET expiration_time = NULL WHERE id = '" . $key . "';");
                    return $value;
                }
            } else {
                if ($futureTime !== null) {
                    $futureTime = manipulate_memory_date($futureTime, $localVariablesTable);

                    if ($futureTime !== false && sql_query("INSERT INTO " . $localVariablesTable . " (id, value_pair, expiration_time) VALUES ('" . $key . "', " . $value . ", '" . $futureTime . "');")) {
                        return $value;
                    }
                } else if (sql_query("INSERT INTO " . $localVariablesTable . " (id, value_pair) VALUES ('" . $key . "', " . $value . ");")) {
                    return $value;
                }
            }
        }
    }
    return false; // Return false when failed
}

// Separator

function hasMemoryLimit1($key, $countLimit, $futureTime = null, $clear = false, $failSafe = true)
{
    global $keyLimitsTable;
    $key = manipulate_memory_key($key, $keyLimitsTable);

    if ($key !== null) {
        $query = sql_query("SELECT id, count FROM " . $keyLimitsTable . " WHERE id = '$key' AND (expiration_time IS NULL OR expiration_time >= '" . time() . "') LIMIT 1;");

        if ($query != null && $query->num_rows === 1) {
            $row = $query->fetch_assoc();
            $count = $row["count"];

            if ($count >= $countLimit) {
                return true;
            }
            sql_query("UPDATE " . $keyLimitsTable . " SET count = '" . ($count + 1) . "' WHERE id = '" . $row["id"] . "';");
        } else {
            $failedInsertion = false;

            if ($futureTime !== null) {
                $futureTime = manipulate_memory_date($futureTime, $keyLimitsTable);

                if ($futureTime !== null) {
                    if (!sql_query("INSERT INTO " . $keyLimitsTable . " (id, count, expiration_time) VALUES ('" . $key . "', '1', '" . $futureTime . "');")
                        && !sql_query("UPDATE " . $keyLimitsTable . " SET count = '1', expiration_time = '" . $futureTime . "' WHERE id = '" . $key . "';")) {
                        $failedInsertion = true;
                    }
                }
            } else if (!sql_query("INSERT INTO " . $keyLimitsTable . " (id, count) VALUES ('" . $key . "', '1');")
                && !sql_query("UPDATE " . $keyLimitsTable . " SET count = '1', expiration_time = NULL WHERE id = '" . $key . "';")) {
                $failedInsertion = true;
            }

            if ($failedInsertion && $failSafe) {
                global $oldRowsToDeleteWhenFull;

                if (!resetTableIfEmptyOrFull($keyLimitsTable)) {
                    sql_query("DELETE FROM $keyLimitsTable ORDER BY id ASC LIMIT $oldRowsToDeleteWhenFull;"); // Delete old rows to make space for new ones
                }
                return has_memory_limit($key, $countLimit, $futureTime); // Do not make it fail-safe as concurrency issues with the overhead-row-count can cause loops
            }
            if ($clear) {
                clearMemory1($keyLimitsTable);
            }
        }
    }
    return false;
}

function hasMemoryCooldown1($key, $futureTime = null, $clear = false, $set = true, $failSafe = true)
{
    global $keyCooldownsTable;
    $key = manipulate_memory_key($key, $keyCooldownsTable);

    if ($key !== null) {
        $query = sql_query("SELECT id FROM " . $keyCooldownsTable . " WHERE id = '$key' AND (expiration_time IS NULL OR expiration_time >= '" . time() . "') LIMIT 1;");

        if ($query != null && $query->num_rows === 1) {
            return true;
        }
        if ($set) {
            $failedInsertion = false;

            if ($futureTime !== null) {
                $futureTime = manipulate_memory_date($futureTime, $keyCooldownsTable);

                if ($futureTime !== null) {
                    if (!sql_query("INSERT INTO " . $keyCooldownsTable . " (id, expiration_time) VALUES ('" . $key . "', '" . $futureTime . "');")
                        && !sql_query("UPDATE " . $keyCooldownsTable . " SET expiration_time = '" . $futureTime . "' WHERE id = '" . $key . "';")) {
                        $failedInsertion = true;
                    }
                }
            } else if (!sql_query("INSERT INTO " . $keyCooldownsTable . " (id) VALUES ('" . $key . "');")
                && !sql_query("UPDATE " . $keyCooldownsTable . " SET expiration_time = NULL WHERE id = '" . $key . "';")) {
                $failedInsertion = true;
            }

            if ($failedInsertion && $failSafe) {
                global $oldRowsToDeleteWhenFull;

                if (!resetTableIfEmptyOrFull($keyCooldownsTable)) {
                    sql_query("DELETE FROM $keyCooldownsTable ORDER BY id ASC LIMIT $oldRowsToDeleteWhenFull;"); // Delete old rows to make space for new ones
                }
                return has_memory_cooldown($key, $futureTime, $set); // Do not make it fail-safe as concurrency issues with the overhead-row-count can cause loops
            }
            if ($clear) {
                clearMemory1($keyCooldownsTable);
            }
        }
    }
    return false;
}

// Separator

function getKeyValuePair1($key, $temporaryRedundancyValue = null, $clear = false)
{
    global $keyValuePairsTable;
    $key = manipulate_memory_key($key, $keyValuePairsTable);

    if ($key !== null) {
        $query = sql_query("SELECT value_pair FROM " . $keyValuePairsTable . " WHERE id = '$key' AND expiration_time >= '" . time() . "' LIMIT 1;");

        if ($query != null && $query->num_rows === 1) {
            if ($clear) {
                clearMemor1y($keyValuePairsTable);
            }
            $pair = $query->fetch_assoc()["value_pair"];
            return $pair === null ? "" : $pair;
        }
        if ($temporaryRedundancyValue !== null) { // Leave it off in very important cases where data accuracy is more important than performance
            setKeyValuePair1($key, $temporaryRedundancyValue, 1); // We do this to prevent potential multiple calls due to processing time that lead to redundant rows and need to check for their existence
        }
    }
    return null;
}

function setKeyValuePair1($key, $value = null, $futureTime = null, $clear = false, $failSafe = true)
{
    $value = manipulate_memory_key($value);

    if ($value !== null) {
        global $keyValuePairsTable;
        $key = manipulate_memory_key($key, $keyValuePairsTable);

        if ($key !== null) {
            $insertion = false;

            if ($futureTime !== null) {
                $futureTime = manipulate_memory_date($futureTime, $keyValuePairsTable);

                if ($futureTime !== false) {
                    if (sql_query("INSERT INTO " . $keyValuePairsTable . " (id, value_pair, expiration_time) VALUES ('" . $key . "', " . $value . ", '" . $futureTime . "');")
                        || sql_query("UPDATE " . $keyValuePairsTable . " SET value_pair = " . $value . ", expiration_time = '" . $futureTime . "' WHERE id = '" . $key . "';")) {
                        $insertion = true;
                    }
                }
            } else if (sql_query("INSERT INTO " . $keyValuePairsTable . " (id, value_pair) VALUES ('" . $key . "', " . $value . ");")
                || sql_query("UPDATE " . $keyValuePairsTable . " SET value_pair = " . $value . ", expiration_time = NULL WHERE id = '" . $key . "';")) {
                $insertion = true;
            }

            if (!$insertion && $failSafe) {
                global $oldRowsToDeleteWhenFull;

                if (!resetTableIfEmptyOrFull($keyValuePairsTable)) {
                    sql_query("DELETE FROM $keyValuePairsTable ORDER BY id ASC LIMIT $oldRowsToDeleteWhenFull;"); // Delete old rows to make space for new ones
                }
                return setKeyValuePair1($key, $value, $futureTime); // Do not make it fail-safe as concurrency issues with the overhead-row-count can cause loops
            }
            if ($clear) {
                clearMemory1($keyValuePairsTable);
            }
            return $insertion;
        }
    }
    return false;
}

// Separator

function clearMemory1($table = null, $all = false, $key = null, $abstractSearch = false, $reset = true)
{
    $continue = false;
    $hasKey = false;

    if ($key === null) {
        $continue = true;
    } else {
        $key = manipulate_memory_key($key);

        if ($key !== null) {
            $continue = true;
            $hasKey = true;
        }
    }

    if ($continue) {
        global $instantCooldownsTable, $keyValuePairsTable, $keyLimitsTable;
        $whereClause = $all ? "" : "expiration_time IS NOT NULL AND expiration_time < '" . time() . "'";

        if ($hasKey) {
            if ($abstractSearch) {
                global $keyStorageTable;
                $query = sql_query("SELECT id FROM " . $keyStorageTable . " WHERE key_value LIKE '%" . $key . "%'");

                if ($query == null || $query->num_rows === 0) {
                    return false;
                }
                $keyIDs = array();

                while ($row = $query->fetch_assoc()) {
                    $keyIDs[] = "id = '" . $row["id"] . "'";
                }
                $whereClause .= ($all ? "" : " AND ") . implode(" AND ", $keyIDs);
            } else {
                $keyID = getIdFromKeyStorage($key, false);

                if ($keyID === null) {
                    return false;
                }
                $whereClause .= ($all ? "" : " AND ") . "id = '" . $keyID . "'";
            }
        } else if ($all) {
            $whereClause = 1;
        }

        if ($table === null) {
            foreach (array(
                         $instantCooldownsTable,
                         $keyValuePairsTable,
                         $keyLimitsTable
                     ) as $table) {
                sql_query("DELETE FROM " . $table . " WHERE " . $whereClause . ";");

                if ($reset) {
                    resetTableIfEmptyOrFull($table);
                }
            }
        } else {
            sql_query("DELETE FROM " . $table . " WHERE " . $whereClause . ";");

            if ($reset) {
                resetTableIfEmptyOrFull($table);
            }
        }
        return true;
    }
    return false;
}
