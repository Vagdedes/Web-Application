<?php

function memory_get_object_cache(mixed $key): IndividualMemoryBlock
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

function has_memory_limit(mixed $key, int|string $countLimit, int|string|null $futureTime = null): bool
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

function has_memory_cooldown(mixed $key, int|string|null $futureTime = null,
                             bool  $set = true, bool $force = false): bool
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

function get_key_value_pair(mixed $key, mixed $temporaryRedundancyValue = null)
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

function set_key_value_pair(mixed $key, mixed $value = null, int|string|null $futureTime = null): bool
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

function clear_memory(array|null $keys,
                      bool       $abstractSearch = false,
                      int|string $stopAfterSuccessfulIterations = 0,
                      ?callable  $valueVerifier = null,
                      mixed      $localSegments = null): void
{
    global $memory_object_cache;

    if ($localSegments === null) {
        //share_clear_memory($keys, $abstractSearch);
    }
    if (!empty($keys)) {
        $hasLimit = is_numeric($stopAfterSuccessfulIterations) && $stopAfterSuccessfulIterations > 0;

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
                                if ($valueVerifier === null || $valueVerifier($memoryBlock->get())) {
                                    $memoryBlock->clear();
                                    unset($memory_object_cache[$segment]);

                                    if ($hasLimit) {
                                        $iterations[$arrayKey]++;

                                        if ($iterations[$arrayKey] == $stopAfterSuccessfulIterations) {
                                            break 2;
                                        }
                                    }
                                }
                            } else if (str_contains($memoryKey, $key)
                                && ($valueVerifier === null || $valueVerifier($memoryBlock->get()))) {
                                $memoryBlock->clear();
                                unset($memory_object_cache[$segment]);

                                if ($hasLimit) {
                                    $iterations[$arrayKey]++;

                                    if ($iterations[$arrayKey] == $stopAfterSuccessfulIterations) {
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
