<?php

function memory_get_object_cache($key): IndividualMemoryBlock
{
    global $memory_object_cache;

    if (!array_key_exists($key, $memory_object_cache)) {
        $memory = new IndividualMemoryBlock($key);
        $memory_object_cache[$key] = $memory;
        return $memory;
    } else {
        return $memory_object_cache[$key];
    }
}

function has_memory_limit($key, $countLimit, $futureTime = null): bool
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 15);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = memory_get_object_cache($memory_reserved_names[1] . $key);
            $object = $memoryBlock->get();

            if ($object !== null && isset($object->original_expiration) && isset($object->count)) {
                $object->count++;

                if ($memoryBlock->set($object, $object->original_expiration)) {
                    return $object->count >= $countLimit;
                } else {
                    return false;
                }
            }
            $object = new stdClass();
            $object->count = 1;
            $object->original_expiration = $futureTime;
            $memoryBlock->set($object, $futureTime);
        }
    }
    return false;
}

function has_memory_cooldown($key, $futureTime = null, $set = true, $force = false): bool
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 30);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = memory_get_object_cache($memory_reserved_names[0] . $key);

            if (!$force && $memoryBlock->exists(!$set)) {
                return true;
            }
            if ($set) {
                $memoryBlock->set(1, $futureTime);
            }
            return false;
        }
    }
    return false;
}

// Separator

function get_key_value_pair($key, $temporaryRedundancyValue = null)
{ // Must call setKeyValuePair() after
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        global $memory_reserved_names;
        $memoryBlock = memory_get_object_cache($memory_reserved_names[2] . $key);
        $object = $memoryBlock->get();

        if ($object !== null) {
            return $object;
        }
        if ($temporaryRedundancyValue !== null) {
            $memoryBlock->set($temporaryRedundancyValue, time() + 1);
        }
    }
    return null;
}

function set_key_value_pair($key, $value = null, $futureTime = null): bool
{ // Must optionally call setKeyValuePair() before
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 3);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = memory_get_object_cache($memory_reserved_names[2] . $key);
            return $memoryBlock->set($value, $futureTime);
        }
    }
    return false;
}

// Separator

function clear_memory($keys, $abstractSearch = false, $stopAfterSuccessfulIterations = 0, $localSegments = null): void
{
    if (!is_array($keys)) {
        return;
    }
    global $memory_object_cache;
    $hasLocalSegments = $localSegments !== null;

    if (!$hasLocalSegments) {
        global $memory_clearance_table;
        $serialize = serialize($keys);
        $tracker = overflow_long((string_to_integer($serialize, true) * 31) + boolean_to_integer($abstractSearch));
        load_sql_database(SqlDatabaseCredentials::MEMORY);

        if (empty(get_sql_query(
            $memory_clearance_table,
            array("tracker"),
            array(
                array("tracker", $tracker),
            ),
            null,
            1
        ))) {
            if (sql_insert(
                $memory_clearance_table,
                array(
                    "tracker" => $tracker,
                    "creation" => time(),
                    "array" => $serialize,
                    "abstract_search" => $abstractSearch
                )
            )) {
                $sql = true;
                $delete = false;
            } else {
                $sql = false;
                // No need to implement delete, never reached
            }
        } else if (set_sql_query(
            $memory_clearance_table,
            array(
                "creation" => time(),
                "array" => $serialize,
                "abstract_search" => $abstractSearch
            ),
            array(
                array("tracker", $tracker),
            ),
            null,
            1
        )) {
            $sql = true;
            $delete = true;
        } else {
            $sql = false;
            // No need to implement delete, never reached
        }

        if ($sql) {
            global $memory_clearance_tracking_table;

            if ($delete) {
                delete_sql_query(
                    $memory_clearance_tracking_table,
                    array(
                        array("tracker", $tracker),
                    ),
                    null,
                    1
                );
            }
            sql_insert(
                $memory_clearance_tracking_table,
                array(
                    "tracker" => $tracker,
                    "identifier" => get_server_identifier(true),
                )
            );
        }
        load_previous_sql_database();
    }

    if (!empty($keys)) {
        $hasLimit = $stopAfterSuccessfulIterations > 0;

        if ($hasLimit) {
            $iterations = array();

            foreach (array_keys($keys) as $key) {
                $iterations[$key] = 0;
            }
        }
        if ($abstractSearch) {
            $segments = is_array($localSegments) ? $localSegments : get_memory_segment_ids();

            if (!empty($segments)) {
                foreach ($segments as $segment) {
                    $memoryBlock = new IndividualMemoryBlock($segment);
                    $memoryKey = $memoryBlock->get("key");

                    if ($memoryKey !== null) {
                        foreach ($keys as $arrayKey => $key) {
                            if (is_array($key)) {
                                foreach ($key as $subKey) {
                                    if (!str_contains($memoryKey, $subKey)) {
                                        continue 2;
                                    }
                                }
                                $memoryBlock->clear();
                                unset($memory_object_cache[$segment]);

                                if ($hasLimit) {
                                    $iterations[$arrayKey]++;

                                    if ($iterations[$arrayKey] === $stopAfterSuccessfulIterations) {
                                        break 2;
                                    }
                                }
                            } else if (str_contains($memoryKey, $key)) {
                                $memoryBlock->clear();
                                unset($memory_object_cache[$segment]);

                                if ($hasLimit) {
                                    $iterations[$arrayKey]++;

                                    if ($iterations[$arrayKey] === $stopAfterSuccessfulIterations) {
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            global $memory_reserved_names;

            foreach ($memory_reserved_names as $name) {
                foreach ($keys as $key) {
                    $name .= $key;
                    $memoryBlock = new IndividualMemoryBlock($name);
                    $memoryBlock->clear();
                    unset($memory_object_cache[$name]);
                }
            }
        }
    } else {
        $segments = is_array($localSegments) ? $localSegments : get_memory_segment_ids();

        if (!empty($segments)) {
            foreach ($segments as $segment) {
                $memoryBlock = new IndividualMemoryBlock($segment);
                $memoryBlock->clear();
                unset($memory_object_cache[$segment]);
            }
        }
    }
}
