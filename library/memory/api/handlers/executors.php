<?php

function has_memory_limit(mixed $key, int|string $countLimit, int|string|null $futureTime = null): bool
{
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $futureTime = manipulate_memory_date($futureTime);

        if ($futureTime !== null) {
            $memoryBlock = new IndividualMemoryBlock($key);
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
        $futureTime = manipulate_memory_date($futureTime);

        if ($futureTime !== null) {
            $memoryBlock = new IndividualMemoryBlock($key);

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

function get_key_value_pair(mixed $key, mixed $temporaryRedundancyValue = null)
{ // Must call setKeyValuePair() after
    $key = manipulate_memory_key($key);

    if ($key !== false) {
        $memoryBlock = new IndividualMemoryBlock($key);
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
        $futureTime = manipulate_memory_date($futureTime);

        if ($futureTime !== null) {
            $memoryBlock = new IndividualMemoryBlock($key);
            return $memoryBlock->set($value, $futureTime);
        }
    }
    return false;
}

// Separator

function clear_memory(?array     $keys = null,
                      bool       $abstractSearch = false,
                      int|string $stopAfterSuccessfulIterations = 0,
                      ?callable  $valueVerifier = null): void
{
    if (!empty($keys)) {
        $hasLimit = is_numeric($stopAfterSuccessfulIterations) && $stopAfterSuccessfulIterations > 0;

        if ($hasLimit) {
            $iterations = array();

            foreach (array_keys($keys) as $key) {
                $iterations[$key] = 0;
            }
        }
        if ($abstractSearch) {
            $segments = get_memory_segment_ids();

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
                                    $memoryBlock->delete();

                                    if ($hasLimit) {
                                        $iterations[$arrayKey]++;

                                        if ($iterations[$arrayKey] == $stopAfterSuccessfulIterations) {
                                            break 2;
                                        }
                                    }
                                }
                            } else if (str_contains($memoryKey, $key)
                                && ($valueVerifier === null || $valueVerifier($memoryBlock->get()))) {
                                $memoryBlock->delete();

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
            foreach ($keys as $key) {
                $memoryBlock = new IndividualMemoryBlock($key);
                $memoryBlock->delete();
            }
        }
    } else {
        $segments = get_memory_segment_ids();

        if (!empty($segments)) {
            foreach ($segments as $segment) {
                $memoryBlock = new IndividualMemoryBlock($segment);
                $memoryBlock->delete();
            }
        }
    }
}
