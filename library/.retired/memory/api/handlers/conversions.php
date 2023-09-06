<?php
require_once '/var/www/.structure/library/memory/api/connect.php';
require_once '/var/www/.structure/library/memory/api/variables.php';
require_once '/var/www/.structure/library/memory/api/handlers/executors.php';

$cacheImplodeCharacter = "+";

function getIdFromKeyStorage($key, $table, $create = true)
{
    global $keyStorageTable;
    $query = sql_query("SELECT id FROM " . $keyStorageTable . " WHERE key_value = '" . $key . "' LIMIT 1;");

    if (isset($query->num_rows) && $query->num_rows === 1) {
        $id = $query->fetch_assoc()["id"];
        sql_query("UPDATE " . $keyStorageTable . " SET last_access_time = '" . time() . "' WHERE id = '" . $id . "';");
        return $id;
    }
    if ($create) {
        global $cooldownPerMemoryTable;
        $timeInText = $cooldownPerMemoryTable[$table];
        $currentTime = time();

        if ($timeInText !== null) {
            $tableMaxCooldownTimeInSeconds_Extended = round((strtotime("+" . $timeInText) - $currentTime) * 1.25);
            $query = sql_query("SELECT id FROM " . $keyStorageTable . " WHERE last_access_time < '" . strtotime("-" . $tableMaxCooldownTimeInSeconds_Extended . " seconds") . "' ORDER BY id ASC LIMIT 1;");

            if (isset($query->num_rows) && $query->num_rows === 1) {
                $id = $query->fetch_assoc()["id"];
                sql_query("UPDATE " . $keyStorageTable . " SET key_value = '" . $key . "', last_access_time = '" . $currentTime . "' WHERE id = '" . $id . "';");
                return $id;
            }
        }

        if (sql_query("INSERT INTO $keyStorageTable (key_value, last_access_time) VALUES ('" . $key . "', '" . $currentTime . "');")) {
            return getIdFromKeyStorage($key, $table);
        } else { // Reset table when the maximum integer has been reached
            $query = sql_query("SELECT id FROM " . $keyStorageTable . " ORDER BY id DESC LIMIT 1");

            if (isset($query->num_rows) && $query->num_rows === 1) {
                global $max32bitInteger;

                if ($query->fetch_assoc()["id"] === $max32bitInteger) {
                    sql_query("TRUNCATE " . $keyStorageTable . ";");
                    clearMemory1(null);
                    return getIdFromKeyStorage($key, $table);
                }
            }
        }
    }
    return null;
}

// Separator

function resetTableIfEmptyOrFull($table)
{
    $query = sql_query("SELECT id FROM " . $table . " ORDER BY id DESC LIMIT 1;");
    $reset = false;
    $numRows = $query == null ? 0 : $query->num_rows;

    if ($numRows === 0) {
        $reset = true;
    } else { // Reset table when the maximum integer has been reached
        global $max32bitInteger, $requiredRowsToTruncate;

        if ($query->fetch_assoc()["id"] === $max32bitInteger
            || $numRows >= $requiredRowsToTruncate[$table] && getLocalVariable(array($table, "truncate_hourly"), "1 hour") === null
            || getLocalVariable(array($table, "truncate_daily"), "1 day") === null) {
            $reset = true;
        }
    }

    if ($reset) {
        sql_query("TRUNCATE " . $table . ";");
        return true;
    }
    return false;
}

// Separator

function manipulateMemoryKey1($key, $table = null)
{
    if ($key === null) {
        return null;
    }
    global $rowKeyMaxLength;

    if ($key === true) {
        $key = "true";
    } else if ($key === false) {
        $key = "false";
    } else if (is_array($key)) {
        global $cacheImplodeCharacter;
        $key = implode($cacheImplodeCharacter, $key);
    } else if (is_object($key)) {
        global $cacheImplodeCharacter;
        $key = implode($cacheImplodeCharacter, get_object_vars($key));
    }
    return strlen($key) > $rowKeyMaxLength ? null : ($table !== null ? getIdFromKeyStorage($key, $table) : $key);
}

function manipulateMemoryValue1($value)
{
    if ($value === null) {
        return null;
    }
    global $valuePairMaxLength;
    $checkLength = true;

    if ($value === true) {
        $value = "'true'";
    } else if ($value === false) {
        $value = "'false'";
    } else if (is_numeric($value)) {
        $value = "'" . $value . "'";
    } else if (is_array($value) || is_object($value)) {
        return manipulate_memory_key(json_encode($value)); // Move it to the last scenario
    } else if (is_string($value) && !isset($value[0])) {
        $value = "NULL";
        $checkLength = false;
    } else {
        $value = "'" . str_replace("'", "\'", $value) . "'";
    }
    return $checkLength && (strlen($value) - 2) > $valuePairMaxLength ? null : $value;
}

function manipulateMemoryDate1($cooldown, $table)
{
    if ($cooldown === null) {
        return null;
    }
    if (is_array($cooldown)) {
        $cooldown = strtotime("+" . implode(" ", $cooldown));

        if ($cooldown === false) {
            return null;
        }
    } else if (is_numeric($cooldown)) {
        $cooldown = time() + 1;
    } else {
        $cooldown = strtotime("+" . $cooldown);

        if ($cooldown === false) {
            return null;
        }
    }
    global $cooldownPerMemoryTable;
    $timeInText = $cooldownPerMemoryTable[$table];
    return $timeInText === null ? $cooldown : min(strtotime("+" . $timeInText), $cooldown);
}
