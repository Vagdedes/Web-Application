<?php

function has_memory_limit($key, $countLimit, $futureTime = null): bool
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 15);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[1] . $key);
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

function has_memory_cooldown($key, $futureTime = null, $set = true, $force = false)
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime, 60 * 30);

        if ($futureTime !== null) {
            global $memory_reserved_names;
            $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[0] . $key);

            if (!$force && $memoryBlock->exists()) {
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
        $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[2] . $key);
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
            $memoryBlock = new IndividualMemoryBlock($memory_reserved_names[2] . $key);
            return $memoryBlock->set($value, $futureTime);
        }
    }
    return false;
}

// Separator

function clear_memory($keys, $abstractSearch = false, $localSegments = null)
{
    $hasLocalSegments = $localSegments !== null;

    if (!$hasLocalSegments) {
        global $memory_clearance_table;
        $tracker = random_number();

        if (sql_insert(
            $memory_clearance_table,
            array(
                "tracker" => $tracker,
                "creation" => time(),
                "array" => serialize($keys),
                "abstract_search" => $abstractSearch
            )
        )) {
            global $memory_clearance_tracking_table;
            sql_insert(
                $memory_clearance_tracking_table,
                array(
                    "tracker" => $tracker,
                    "identifier" => get_server_identifier(),
                )
            );
        }
    }
    foreach ($keys as $position => $key) {
        $key = manipulate_memory_key($key);

        if ($key === false) {
            unset($keys[$position]);
        } else {
            $keys[$position] = $key;
        }
    }

    if (!empty($keys)) {
        global $memory_reserved_names;

        if ($abstractSearch) {
            $segments = is_array($localSegments) ? $localSegments : get_memory_segment_ids();

            if (!empty($segments)) {
                foreach ($keys as $arrayKey => $key) {
                    $keys[$arrayKey] = serialize($key);
                }
                foreach ($segments as $segment) {
                    $memoryBlock = new IndividualMemoryBlock($segment);
                    $memoryKey = $memoryBlock->get("key");

                    if ($memoryKey !== null) {
                        foreach ($keys as $key) {
                            if (strpos($memoryKey, $key) !== false) {
                                $memoryBlock->clear();
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($keys as $key) {
                foreach ($memory_reserved_names as $name) {
                    $memoryBlock = new IndividualMemoryBlock($key . $name);
                    $memoryBlock->clear();
                }
            }
        }
    }
}
