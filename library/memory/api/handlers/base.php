<?php

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
function reserve_memory_key($name, $key): void
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
    global $memory_reserved_keys;
    $niddle = "max number of segments = ";
    $integer = substr(shell_exec("ipcs -l | grep '$niddle'"), strlen($niddle), -1) - sizeof($memory_reserved_keys);
    return $multiplier !== null ? round($integer * $multiplier) : $integer;
}

function get_memory_segment_ids(): array
{
    global $memory_segments_table;
    $time = time();
    $identifer = get_server_identifier();
    load_sql_database(SqlDatabaseCredentials::MEMORY);
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
                load_previous_sql_database();
                return $query;
            }
        }
    }
    global $memory_permissions_string;
    $stringToFix = "echo 32768 >/proc/sys/kernel/shmmni";
    $oldCommand = "ipcs -m | grep 'www-data.*$memory_permissions_string'";
    $array = explode(chr(32), shell_exec("ipcs -m"));

    if (!empty($array)) {
        foreach ($array as $key => $value) {
            if (empty($value) || is_numeric($value) || $value[0] === "w") {
                unset($array[$key]);
            } else {
                $array[$key] = @hexdec($value);
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
    load_previous_sql_database();
    return $array;
}

function has_reached_maximum_segment_ids(): bool
{
    global $memory_reserved_keys;
    $memoryBlock = new IndividualMemoryBlock($memory_reserved_keys[0]);

    if (!$memoryBlock->exists(false)) {
        $result = sizeof(get_memory_segment_ids()) >= get_memory_segment_limit(0.999); // 99.9% due to potential multi-threading and the one-second cooldown
        $memoryBlock->set($result, time() + 1); // Cooldown before next check
        return $result;
    } else {
        return $memoryBlock->get() === true;
    }
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
        //shmop_close($block); DEPRECATED
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
    private mixed $originalKey;
    private int $key;

    /**
     * @throws Exception
     */
    public function __construct($key)
    {
        if (is_integer($key)) { // Used for reserved or existing keys
            $keyToInteger = $key;
            $this->originalKey = $key;
        } else {
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

    public function getOriginalKey(): mixed
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
        $block = $this->internalGetBlock($exceptionID, $this->key, $this->originalKey, $memory_starting_bytes, false);
        return $this->internalGetBlockSize($exceptionID, $this->originalKey, $block);
    }

    /**
     * @throws Exception
     */
    private function getRaw($removeIfExpired = true)
    {
        global $memory_starting_bytes;
        $exceptionID = 3;
        $block = $this->internalGetBlock($exceptionID, $this->key, $this->originalKey, $memory_starting_bytes, false);

        if (!$block) {
            return null;
        }
        $readMapBytes = $this->readBlock(
            $block,
            max($this->internalGetBlockSize($exceptionID, $this->originalKey, $block), $memory_starting_bytes)
        );

        if (!$readMapBytes) {
            return false;
        }
        global $memory_filler_character;
        $rawData = trim($readMapBytes, $memory_filler_character);

        if (strlen($rawData) >= 21) { // Minimum length of serialized and deflated object.
            $value = @gzinflate($rawData);

            if ($value !== false) {
                $object = @unserialize($value);

                if (isset($object->expiration)) {
                    if ($object->expiration === false || $object->expiration >= time()) {
                        return $object;
                    }
                    if ($removeIfExpired) {
                        $this->internalClose();
                    }
                } else {
                    $this->throwException("Unable to use individual-memory-block object of key '" . $this->originalKey . "': " . $value);
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
    public function get($objectKey = "value", $removeIfExpired = true)
    {
        $raw = $this->getRaw($removeIfExpired);
        return $raw?->{$objectKey};
    }

    /**
     * @throws Exception
     */
    public function exists($removeIfExpired = true): bool
    {
        return $this->getRaw($removeIfExpired) !== null;
    }

    /**
     * @throws Exception
     */
    public function clear($ifExpired = false): bool
    {
        if (!is_reserved_memory_key($this->key)
            && (!$ifExpired || $this->getRaw() === null)) {
            if (!$this->internalClose(null, true)) {
                $this->throwException("Unable to manually close current individual-memory-block: " . $this->originalKey, false);
            }
            return true;
        } else {
            return false;
        }
    }

    // Separator

    /**
     * @throws Exception
     */
    public function set($value, $expiration = false, $ifEmpty = false): bool
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
        $objectToTextRaw = serialize($object);
        $objectToText = @gzdeflate($objectToTextRaw, 9);

        if ($objectToText === false) {
            $this->throwException("Failed to deflate string '" . $objectToTextRaw . "' of individual-memory-block: " . $this->originalKey);
        }
        $objectToTextLength = strlen($objectToText);
        $block = $this->internalGetBlock($exceptionID, $this->key, $this->originalKey, $memory_starting_bytes, false, true);
        $bytesSize = max($this->internalGetBlockSize($exceptionID, $this->originalKey, $block), $memory_starting_bytes); // check default

        if ($objectToTextLength > $bytesSize) {
            if (!$this->internalClose($block, true)) {
                $this->throwException("Unable to close old individual-memory-block: " . $this->originalKey);
            }
            $oldBytesSize = $bytesSize;
            $bytesSize = max($bytesSize + $memory_starting_bytes, $objectToTextLength);
            $block = $this->internalGetBlock(2, $this->key, $this->originalKey, $bytesSize, true, true); // open bigger

            if (!$block) {
                return false;
            } else if (is_array($block)) { // Revert to old bytes if php did not close the previous block
                $bytesSize = $oldBytesSize;
                $block = $block[0];
            }
            $readMapBytes = $this->readBlock($block, $bytesSize);

            if (!$readMapBytes) {
                return false;
            }
        } else if (!$block) {
            if (!is_reserved_memory_key($this->key)
                && has_reached_maximum_segment_ids()
                && !$this->removeExpired()) {
                return false;
            }
            $block = $this->internalGetBlock($exceptionID, $this->key, $this->originalKey, $bytesSize, true, true); // open default

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
            $this->throwException("Unable to write to individual-memory-block: " . $this->originalKey);
        }
        return true;
    }

    /**
     * @throws Exception
     */
    private function removeExpired(): bool
    {
        $segments = get_memory_segment_ids();

        if (!empty($segments)) {
            $sortedByCreation = array();

            foreach ($segments as $segment) {
                $memoryBlock = new IndividualMemoryBlock($segment);

                if ($memoryBlock->clear(true)) {
                    return true;
                } else {
                    $time = $memoryBlock->get("creation");

                    if ($time !== null) {
                        $sortedByCreation[$time] = $memoryBlock;
                    }
                }
            }

            if (!empty($sortedByCreation)) {
                ksort($sortedByCreation); // Sort in ascending order, so we start from the least recent

                foreach ($sortedByCreation as $memoryBlock) {
                    if ($memoryBlock->clear()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @throws Exception
     */
    private function internalClose($block = null, $ignoreCreation = false): bool
    {
        if ($block === null) {
            global $memory_starting_bytes;
            $block = $this->internalGetBlock(5, $this->key, $this->originalKey, $memory_starting_bytes, false);
        }
        if ($block) {
            return shmop_delete($block);
        } else {
            return $ignoreCreation;
        }
        //shmop_close($block);
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

    private function readBlock($block, $bytesSize): bool|string
    {
        try {
            global $max_32bit_Integer;

            if ($bytesSize >= 0 && $bytesSize <= $max_32bit_Integer) {
                return @shmop_read($block, 0, $bytesSize);
            } else {
                return false;
            }
        } catch (Error|Exception $ignored) {
            return false;
        }
    }
}