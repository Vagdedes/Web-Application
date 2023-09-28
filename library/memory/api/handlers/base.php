<?php

foreach ($memory_reserved_keys as $memory_reserved_key) {
    try {
        $memoryBlock = new IndividualMemoryBlock($memory_reserved_key);
        $memoryBlock->internalSet($memory_reserved_value, 0, true);
    } catch (Exception $e) {
    }
}

function get_reserved_memory_names(): array
{
    global $memory_reserved_keys;
    $array = array();

    foreach (array_keys($memory_reserved_keys) as $key) {
        if (is_string($key)) {
            $array[] = $key;
        }
    }
    return $array;
}

/**
 * @throws Exception
 */
function reserve_memory_key($name, $key)
{
    if (!is_string($name)) {
        throw new Exception("Tried to reserve name that's not a string: " . $name);
    }
    if (!is_integer($key)) {
        throw new Exception("Tried to reserve key for name '" . $name . "' that's not an integer: " . $key);
    }
    global $memory_reserved_keys;

    if (isset($memory_reserved_keys[$name])) {
        $existingKey = $memory_reserved_keys[$name];

        if ($existingKey !== $key) {
            throw new Exception("Tried to change memory key of name '" . $name . "' from '$existingKey' to '$key'");
        }
        return;
    }
    $memory_reserved_keys[$name] = $key;
}

/**
 * @throws Exception
 */
function get_reserved_memory_key($name): int
{
    global $memory_reserved_keys;

    if (!isset($memory_reserved_keys[$name])) {
        throw new Exception("Tried to use name that is not reserved: " . $name);
    }
    return $memory_reserved_keys[$name];
}

function is_reserved_memory_key($key): bool
{
    global $memory_reserved_keys;
    return in_array($key, $memory_reserved_keys);
}

// Separator

function get_memory_segment_limit($multiplier = null): int
{
    $niddle = "max number of segments = ";
    $integer = substr(shell_exec("ipcs -l | grep '$niddle'"), strlen($niddle), -1);
    return $multiplier !== null ? round($integer * $multiplier) : $integer;
}

function get_memory_segment_ids(): array
{
    global $memory_segments_table;
    $time = time();
    $identifer = get_server_identifier();
    $query = get_sql_query(
        $memory_segments_table,
        array("array", "next_repetition"),
        array(
            array("identifier", $identifer)
        ),
        null,
        1
    );
    $empty = empty($query);

    if (!$empty) {
        $query = $query[0];

        if ($time <= $query->next_repetition) {
            $query = @unserialize($query->array);

            if (is_array($query)) {
                return $query;
            }
        }
    }
    global $memory_permissions_string;
    $stringToFix = "echo 32766 >/proc/sys/kernel/shmmni";
    $oldCommand = "ipcs -m | grep 'www-data.*$memory_permissions_string'";
    $array = explode(chr(32), shell_exec("ipcs -m"));

    if (!empty($array)) {
        foreach ($array as $key => $value) {
            if (empty($value) || is_numeric($value) || $value[0] === "w") {
                unset($array[$key]);
            } else {
                $array[$key] = hexdec($value);
            }
        }
    }
    if ($empty) {
        sql_insert(
            $memory_segments_table,
            array(
                "identifier" => $identifer,
                "array" => serialize($array),
                "next_repetition" => time() + 1
            )
        );
    } else {
        set_sql_query(
            $memory_segments_table,
            array(
                "array" => serialize($array),
                "next_repetition" => time() + 1
            ),
            array(
                array("identifier", $identifer)
            ),
            null,
            1
        );
    }
    return $array;
}

// Separator

function map_to_string($array): string
{
    $string = "";

    foreach ($array as $key => $value) {
        $dataToStore = @gzdeflate($value, 9);

        if ($dataToStore !== false) {
            $string .= $key . "\r" . $dataToStore . "\r";
        } else {
            throw new Exception("Failed to deflate string: " . $value);
        }
    }
    return $string;
}

function string_to_map($string): array
{
    $explode = preg_split("/(\r)/", $string);
    $array = array();
    $previousValue = null;

    foreach ($explode as $position => $value) {
        if (($position + 1) % 2 == 0) {
            $storedData = @gzinflate($value);

            if ($storedData !== false) {
                $array[$previousValue] = $storedData;
            } else {
                throw new Exception("Failed to inflate string: " . $value);
            }
        } else {
            $previousValue = $value;
        }
    }
    return $array;
}

// Separator

class ThreadMemoryBlock
{
    // 23 is the length of the max 64-bit negative integer
    private $key, $block, $noLock, $expiration;

    /**
     * @throws Exception
     */
    public function __construct($name, $expiration)
    {
        global $memory_permissions;
        $this->key = get_reserved_memory_key($name);
        $this->expiration = $expiration; // 30 seconds

        $noLock = serialize(0);
        $remainingBytes = 23 - strlen($noLock);

        if ($remainingBytes > 0) {
            global $memory_filler_character;
            $noLock .= str_repeat($memory_filler_character, $remainingBytes);
        }
        $this->noLock = $noLock;

        $block = @shmop_open($this->key, "c", $memory_permissions, 23);

        if (!$block) {
            $this->throwException("Unable to open thread-memory-block: " . $this->key, false);
        }
        $this->block = $block;
    }

    /**
     * @throws Exception
     */
    public function lock()
    {
        $serialized = serialize(time() + $this->expiration);
        $remainingBytes = 23 - strlen($serialized);

        if ($remainingBytes > 0) {
            global $memory_filler_character;
            $serialized .= str_repeat($memory_filler_character, $remainingBytes);
        }
        $block = $this->block; // Prepare before to save performance

        while ($this->isLocked()) {
        }
        if (shmop_write($block, $serialized, 0) !== 23) {
            $this->throwException("Unable to write to thread-memory-block: " . $this->key);
        }
    }

    /**
     * @throws Exception
     */
    public function unlock()
    {
        if (shmop_write($this->block, $this->noLock, 0) !== 23) {
            $this->throwException("Unable to write to thread-memory-block: " . $this->key);
        }
    }

    /**
     * @throws Exception
     */
    public function isLocked(): bool
    {
        $readMapBytes = shmop_read($this->block, 0, 23);

        if (!$readMapBytes) {
            $this->throwException("Unable to read thread-memory-block: " . $this->key);
        }
        return @unserialize($readMapBytes) >= time();
    }

    /**
     * @throws Exception
     */
    public function destroy(): bool
    {
        $block = $this->block;

        if (!shmop_delete($block)) {
            $this->throwException("Unable to close thread-memory-block: " . $this->key, false);
        }
        shmop_close($block);
        return true;
    }

    /**
     * @throws Exception
     */
    private function throwException($exception, $destroy = true)
    {
        if ($destroy) {
            $this->destroy();
        }
        throw new Exception($exception);
    }
}

// Separator

class IndividualMemoryBlock
{
    private $key, $originalKey;

    /**
     * @throws Exception
     */
    public function __construct($key)
    {
        if (is_integer($key)) { // Used for reserved or existing keys
            $keyToInteger = $key;
            $this->originalKey = $key;
        } else {
            if (!function_exists("string_to_integer")) {
                $this->throwException("Function 'string_to_integer' doesn't exist or isn't included.");
            }
            $keyToInteger = string_to_integer($key);

            if (is_reserved_memory_key($keyToInteger)) {
                $this->throwException("Tried using reserved key '" . $keyToInteger . "' as individual-memory-block.");
            }
            $this->originalKey = $key;
        }
        $this->key = $keyToInteger;
    }

    public function getKey(): int
    {
        return $this->key;
    }

    public function getOriginalKey(): int
    {
        return $this->originalKey;
    }

    /**
     * @throws Exception
     */
    public function getSize(): int
    {
        global $memory_starting_bytes;
        $exceptionID = 4;
        $originalKey = $this->originalKey;
        $block = $this->internalGetBlock($exceptionID, $this->key, $originalKey, $memory_starting_bytes, false);
        return $this->internalGetBlockSize($exceptionID, $originalKey, $block);
    }

    /**
     * @throws Exception
     */
    public function set($value, $expiration = false): bool
    {
        $bool = $this->internalSet($value, $expiration);
        $this->lookForExpiredSegments();
        return $bool;
    }

    /**
     * @throws Exception
     */
    public function getRaw()
    {
        global $memory_starting_bytes;
        $exceptionID = 3;
        $key = $this->key;
        $originalKey = $this->originalKey;
        $block = $this->internalGetBlock($exceptionID, $key, $originalKey, $memory_starting_bytes, false);

        if (!$block) {
            return null;
        }
        try {
            $readMapBytes = shmop_read($block, 0, max($this->internalGetBlockSize($exceptionID, $originalKey, $block), $memory_starting_bytes));
        } catch (Exception $ignored) {
            return null;
        }
        if (!$readMapBytes) {
            $this->throwException("Unable to read individual-memory-block: " . $originalKey);
        }
        global $memory_filler_character;
        $rawData = trim($readMapBytes, $memory_filler_character);

        if (strlen($rawData) >= 21) { // Minimum length of serialized and deflated object.
            $value = @gzinflate($rawData);

            if ($value !== false) {
                $object = @unserialize($value);

                if (is_object($object)) {
                    if (isset($object->expiration)
                        && isset($object->creation)
                        && isset($object->value)
                        && isset($object->key)) {
                        $expiration = $object->expiration;

                        if ($expiration === false || $expiration >= time()) {
                            if ($this->originalKey === $this->key) { // Update the key when possible & needed
                                $this->originalKey = $object->key;
                            }
                            return $object;
                        }
                        $this->internalClose();
                    } else {
                        $this->throwException("Unable to use individual-memory-block object of key '" . $originalKey . "': " . $value);
                    }
                } else {
                    $this->throwException("Unable to restore individual-memory-block object of key '" . $originalKey . "': " . $value);
                }
            } else {
                //$this->throwException("Failed to inflate string '" . $rawData . "' of individual-memory-block: " . $originalKey);
            }
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function get($objectKey = "value")
    {
        $raw = $this->getRaw();
        return $raw !== null ? $raw->{$objectKey} : null;
    }

    /**
     * @throws Exception
     */
    public function exists(): bool
    {
        return $this->get() !== null;
    }

    /**
     * @throws Exception
     */
    public function clear()
    {
        if (!$this->internalClose()) {
            $this->throwException("Unable to manually close current individual-memory-block: " . $this->originalKey, false);
        }
    }

    // Separator

    /**
     * @throws Exception
     */
    public function internalSet($value, $expiration, $ifEmpty = false): bool
    {
        if ($ifEmpty && $this->getRaw() !== null) {
            return false;
        }
        global $memory_starting_bytes;

        $object = new stdClass();
        $object->key = $this->originalKey;
        $object->value = $value;
        $object->creation = time();
        $object->expiration = is_numeric($expiration) ? $expiration : false;

        $exceptionID = 1;
        $key = $this->key;
        $originalKey = $this->originalKey;
        $objectToTextRaw = serialize($object);
        $objectToText = @gzdeflate($objectToTextRaw, 9);

        if ($objectToText === false) {
            $this->throwException("Failed to deflate string '" . $objectToTextRaw . "' of individual-memory-block: " . $originalKey);
        }
        $objectToTextLength = strlen($objectToText);
        $block = $this->internalGetBlock($exceptionID, $key, $originalKey, $memory_starting_bytes, false, true);
        $bytesSize = max($this->internalGetBlockSize($exceptionID, $originalKey, $block), $memory_starting_bytes); // check default

        if ($objectToTextLength > $bytesSize) {
            if (!$this->internalClose($block)) {
                $this->throwException("Unable to close old individual-memory-block: " . $originalKey);
            }
            $oldBytesSize = $bytesSize;
            $bytesSize = max($bytesSize + $memory_starting_bytes, $objectToTextLength);
            $block = $this->internalGetBlock(2, $key, $originalKey, $bytesSize, true, true); // open bigger

            if (!$block) {
                return false;
            } else if (is_array($block)) { // Revert to old bytes if php did not close the previous block
                $bytesSize = $oldBytesSize;
                $block = $block[0];
            }
            try {
                $readMapBytes = shmop_read($block, 0, $bytesSize);
            } catch (Exception $ignored) {
                return false;
            }

            if (!$readMapBytes) {
                $this->throwException("Unable to read replacement individual-memory-block: " . $originalKey);
            }
        } else if (!$block) {
            $block = $this->internalGetBlock($exceptionID, $key, $originalKey, $bytesSize, true, true); // open default

            if (!$block) {
                return false;
            }
        }
        $remainingBytes = $bytesSize - $objectToTextLength;

        if ($remainingBytes > 0) {
            global $memory_filler_character;
            $objectToText .= str_repeat($memory_filler_character, $remainingBytes);
        }
        $bytesWritten = shmop_write($block, $objectToText, 0);

        if ($bytesWritten !== $bytesSize) {
            $this->throwException("Unable to write to individual-memory-block: " . $originalKey);
        }
        return true;
    }

    /**
     * @throws Exception
     */
    private function lookForExpiredSegments()
    {
        global $memory_reserved_keys;
        $memoryBlock = new IndividualMemoryBlock($memory_reserved_keys[0]);

        if (!$memoryBlock->exists()) {
            global $memory_reserved_value;
            $memoryBlock->internalSet($memory_reserved_value, time() + 30); // Cooldown before next clearance
            $segments = get_memory_segment_ids();
            $segmentAmount = sizeof($segments);

            if ($segmentAmount > 0) {
                if ($segmentAmount >= get_memory_segment_limit(0.9)) {
                    $sortedByCreation = array();

                    foreach ($segments as $segment) {
                        $memoryBlock = new IndividualMemoryBlock($segment);
                        $time = $memoryBlock->get("creation");

                        if ($time !== null) {
                            $sortedByCreation[$time] = $memoryBlock;
                        }
                    }
                    $sortedSegmentAmount = sizeof($sortedByCreation);

                    if ($sortedSegmentAmount > 0) {
                        $sortedSegmentAmount /= 3; // Modify it so it can act as a clearing limit
                        sort($sortedByCreation); // Sort in ascending order, so we start from the least recent

                        foreach ($sortedByCreation as $counter => $memoryBlock) {
                            $memoryBlock->clear();

                            if ($counter >= $sortedSegmentAmount) {
                                break;
                            }
                        }
                    }
                } else {
                    foreach ($segments as $segment) {
                        $memoryBlock = new IndividualMemoryBlock($segment);
                        $memoryBlock->get(); // Call to clear if expired
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function internalClose($block = null): bool
    {
        if ($block === null) {
            global $memory_starting_bytes;
            $block = $this->internalGetBlock(5, $this->key, $this->originalKey, $memory_starting_bytes, false);
        }

        if (!$block) {
            return true;
        }
        if (!shmop_delete($block)) {
            return false;
        }
        shmop_close($block);
        return true;
    }

    /**
     * @throws Exception
     */
    private function internalGetBlock($exceptionID, $key, $originalKey, $bytes = -1, $create = true, $write = false)
    {
        global $memory_permissions, $memory_starting_bytes;
        $bytes = max($memory_starting_bytes, $bytes);
        $block = @shmop_open($key, $write ? "w" : "a", $memory_permissions, $bytes);

        if (!$block && $create) {
            $block = @shmop_open($key, "c", $memory_permissions, $bytes);

            if (!$block) {
                $errors = error_get_last();
                $hasErrorKey = array_key_exists("message", $errors);
                $throwException = true;

                if ($hasErrorKey) {
                    global $memory_segment_ignore_errors;

                    if (!empty($memory_segment_ignore_errors)) {
                        $errorMessage = $errors["message"];

                        foreach ($memory_segment_ignore_errors as $ignoreError) {
                            if (strpos($errorMessage, $ignoreError) !== false) {
                                $throwException = false;
                                break;
                            }
                        }
                    }
                }

                if ($throwException) {
                    $this->throwException(
                        "Unable to open/read individual-memory-block (" . $exceptionID . "): " . $originalKey,
                        true,
                        $errors
                    );
                }
            }
        }
        return $block;
    }

    /**
     * @throws Exception
     */
    private function internalGetBlockSize($exceptionID, $originalKey, $block): int
    {
        if (!$block) {
            return -1;
        }
        $size = shmop_size($block);

        if (!$size) {
            $this->throwException("Failed to read size of individual-memory-block (" . $exceptionID . "): " . $originalKey);
        }
        return $size;
    }

    /**
     * @throws Exception
     */
    private function throwException($exception, $close = true, $errors = null)
    {
        if ($close) {
            $this->internalClose();
        }
        if ($errors === null) {
            $errors = error_get_last();
        }
        throw new Exception($exception . (!empty($errors) ? " [" . $errors["message"] . "]" : ""));
    }
}