<?php
$memory_reserved_keys = array(
    0 => 0xff1, // 4081
);
$memory_permissions = 0644;
$memory_filler_character = " ";
$memory_starting_bytes = 1024;

try {
    $memory_thread_object = new ThreadMemoryBlock(0, 30);
} catch (Exception $exception) {
    error_log($exception);
    exit();
}

function getReservedNames(): array
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
function reserveMemoryKey($name, $key)
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
function getReservedMemoryKey($name): int
{
    global $memory_reserved_keys;

    if (!isset($memory_reserved_keys[$name])) {
        throw new Exception("Tried to use name that is not reserved: " . $name);
    }
    return $memory_reserved_keys[$name];
}

function isReservedMemoryKey($key): bool
{
    global $memory_reserved_keys;

    foreach ($memory_reserved_keys as $memory_reserved_key) {
        if ($memory_reserved_key === $key) {
            return true;
        }
    }
    return false;
}

// Separator

function arrayToString($array): string
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

function stringToArray($string): array
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
    function __construct($name, $expiration)
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

        $block = shmop_open($this->key, "c", $memory_permissions, 23);

        if (!$block) {
            $this->throwException("Unable to open thread-memory-block: " . $this->key, false);
        }
        $this->block = $block;
    }

    /**
     * @throws Exception
     */
    function lock()
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
    function unlock()
    {
        if (shmop_write($this->block, $this->noLock, 0) !== 23) {
            $this->throwException("Unable to write to thread-memory-block: " . $this->key);
        }
    }

    /**
     * @throws Exception
     */
    function isLocked(): bool
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
    function destroy(): bool
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

class ListMemoryBlock
{
    private $key, $map, $modified, $end, $mapClearTime;

    /**
     * @throws Exception
     */
    function __construct($name)
    {
        global $memory_thread_object;
        $this->key = get_reserved_memory_key($name);
        $this->map = null;
        $this->mapClearTime = -1;
        $this->modified = false;
        $this->end = false;
        $memory_thread_object->lock();
    }

    /**
     * @throws Exception
     */
    function __destruct()
    { // In case we forget to end the process of the object
        $this->endProcess();
    }

    /**
     * @throws Exception
     */
    function getKey(): int
    {
        $this->verifyEnd("getKey");
        return $this->key;
    }

    /**
     * @throws Exception
     */
    function getSize(): int
    {
        $this->verifyEnd("getSize");
        global $memory_starting_bytes;
        $exceptionID = 4;
        $key = $this->key;
        $block = $this->internalGetBlock($exceptionID, $key, $memory_starting_bytes, false);
        return $this->internalGetBlockSize($exceptionID, $key, $block);
    }

    /**
     * @throws Exception
     */
    function getMap(): array
    {
        $this->verifyEnd("getMap");
        $map = $this->internalGetMap();

        if (sizeof($map) > 0) {
            $toReturn = array();

            foreach ($map as $key => $object) {
                $toReturn[$key] = $object->value;
            }
            return $toReturn;
        }
        return $map;
    }

    /**
     * @throws Exception
     */
    function endProcess($return = null, $destroy = false)
    {
        if (!$this->end) {
            $this->end = true;
            global $memory_thread_object;

            if ($this->modified) {
                $map = $this->internalGetMap();

                if (sizeof($map) > 0) {
                    foreach ($map as $key => $value) {
                        $map[$key] = json_encode($value);
                    }
                }
                $this->internalSetMap($map);
            }

            if ($destroy) {
                return $this->internalDestroy($return);
            }
            $memory_thread_object->unlock();
        }
        return $return;
    }

    /**
     * @throws Exception
     */
    function existsInMap($key)
    {
        $this->verifyKey($key, "existsInMap");
        return isset($this->internalGetMap()[$key]);
    }

    /**
     * @throws Exception
     */
    function findInMap($key)
    {
        $this->verifyKey($key, "findInMap");
        $map = $this->internalGetMap();
        return isset($map[$key]) ? $map[$key]->value : null;
    }

    /**
     * @throws Exception
     */
    function setInMap($key, $value, $expiration = false)
    {
        $this->verifyKey($key, "setInMap");
        $this->internalCacheMap();
        $object = new stdClass();
        $object->expiration = is_numeric($expiration) ? $expiration : false;
        $object->value = $value;
        $this->map[$key] = $object;
        $this->modified = true;
    }

    /**
     * @throws Exception
     */
    function removeFromMap($key)
    {
        $this->verifyKey($key, "removeFromMap");
        $this->internalCacheMap();

        if (isset($this->map[$key])) {
            unset($this->map[$key]);
            $this->modified = true;
        }
    }

    /**
     * @throws Exception
     */
    function removeMultipleFromMap($keys)
    {
        $this->internalCacheMap();
        $map = $this->map;
        $modified = false;

        foreach ($keys as $key) {
            $this->verifyKey($key, "removeMultipleFromMap");

            if (isset($map[$key])) {
                unset($map[$key]);
                $modified = true;
            }
        }
        $this->map = $map;

        if ($modified) {
            $this->modified = true;
        }
    }

    /**
     * @throws Exception
     */
    function clearMap()
    {
        $this->verifyEnd("clearMap");

        if (sizeof($this->map) !== 0) {
            $this->map = array();
            $this->modified = true;
        }
    }

    // Separator

    /**
     * @throws Exception
     */
    private function verifyKey($key, $method)
    {
        $this->verifyEnd($method);

        if (!is_string($key)) {
            $this->throwException("Tried using list-memory-block key in '$method' method that's not a string");
        }
    }

    /**
     * @throws Exception
     */
    private function verifyEnd($method)
    {
        if ($this->end) {
            $this->throwException("Tried using list-memory-block '$method' method after its end");
        }
    }

    /**
     * @throws Exception
     */
    private function internalDestroy($return = null)
    {
        global $memory_thread_object;

        if (!$this->internalClose()) {
            $memory_thread_object->unlock();
            $this->throwException("Unable to manually close current list-memory-block: " . $this->key, false);
        }
        $memory_thread_object->unlock();
        return $return;
    }

    /**
     * @throws Exception
     */
    private function internalClose($block = null): bool
    {
        if ($block === null) {
            global $memory_starting_bytes;
            $block = $this->internalGetBlock(5, $this->key, $memory_starting_bytes, false);
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
    private function internalGetMap(): array
    {
        $time = time();

        if ($this->mapClearTime === $time) {
            return $this->map;
        }
        $this->mapClearTime = $time;
        $map = $this->internalCacheMap();

        if (sizeof($map) > 0) {
            $changed = false;
            $toReturn = array();

            foreach ($map as $key => $object) {
                $expiration = $object->expiration;

                if ($expiration !== false && $expiration < $time) { // Expired
                    $changed = true;
                } else {
                    $toReturn[$key] = $object;
                }
            }

            if ($changed) {
                $this->map = $toReturn;
                $this->modified = true;
            }
            return $toReturn;
        }
        return $map;
    }

    /**
     * @throws Exception
     */
    private function internalSetMap($map)
    {
        global $memory_starting_bytes;
        $exceptionID = 1;
        $key = $this->key;
        $mapToText = map_to_string($map);
        $mapToTextLength = strlen($mapToText);
        $block = $this->internalGetBlock($exceptionID, $key, $memory_starting_bytes, false, true);
        $bytesSize = max($this->internalGetBlockSize($exceptionID, $key, $block), $memory_starting_bytes); // check default

        if ($mapToTextLength > $bytesSize) {
            if (!$this->internalClose($block)) {
                $this->throwException("Unable to close old list-memory-block: " . $key);
            }
            $oldBytesSize = $bytesSize;
            $bytesSize = max($bytesSize + $memory_starting_bytes, $mapToTextLength);
            $block = $this->internalGetBlock(2, $key, $bytesSize, true, true); // open bigger

            if (is_array($block)) { // Revert to old bytes if php did not close the previous block
                $bytesSize = $oldBytesSize;
                $block = $block[0];
            }
            $readMapBytes = shmop_read($block, 0, $bytesSize);

            if (!$readMapBytes) {
                $this->throwException("Unable to read replacement list-memory-block: " . $key);
            }
        } else if (!$block) {
            $block = $this->internalGetBlock($exceptionID, $key, $bytesSize, true, true); // open default
        }
        $remainingBytes = $bytesSize - $mapToTextLength;

        if ($remainingBytes > 0) {
            global $memory_filler_character;
            $mapToText .= str_repeat($memory_filler_character, $remainingBytes);
        }
        $bytesWritten = shmop_write($block, $mapToText, 0);

        if ($bytesWritten !== $bytesSize) {
            $this->throwException("Unable to write to list-memory-block: " . $key);
        }
    }

    /**
     * @throws Exception
     */
    private function internalCacheMap(): array
    {
        global $memory_starting_bytes;

        if ($this->map !== null) {
            return $this->map;
        }
        $exceptionID = 3;
        $key = $this->key;
        $block = $this->internalGetBlock($exceptionID, $key, $memory_starting_bytes, false);

        if (!$block) {
            $map = array();
            $this->mapClearTime = time();
            $this->map = $map;
            return $map;
        }
        $readMapBytes = shmop_read($block, 0, max($this->internalGetBlockSize($exceptionID, $key, $block), $memory_starting_bytes));

        if (!$readMapBytes) {
            $this->throwException("Unable to read list-memory-block: " . $key);
        }
        global $memory_filler_character;
        $map = string_to_map(trim($readMapBytes, $memory_filler_character));

        if (sizeof($map) > 0) {
            $time = time();
            $this->mapClearTime = $time;

            foreach ($map as $mapKey => $value) {
                $json = json_decode($value);

                if (is_object($json)) {
                    if (isset($json->expiration) && isset($json->value)) {
                        $expiration = $json->expiration;

                        if ($expiration === false || $expiration >= $time) {
                            $map[$mapKey] = $json;
                        } else {
                            unset($map[$mapKey]);
                        }
                    } else {
                        unset($map[$mapKey]);
                        $this->throwException("Unable to use list-memory-block object of key '" . $key . "': (" . $mapKey . ", " . $value . ")");

                    }
                } else { // fix
                    unset($map[$mapKey]);
                    //$this->throwException("Unable to restore list-memory-block object of key '" . $key . "': (" . $mapKey . ", " . $value . ")");
                }
            }
        } else {
            $this->mapClearTime = time();
        }
        $this->map = $map;
        return $map;
    }

    /**
     * @throws Exception
     */
    private function internalGetBlock($exceptionID, $key, $bytes = -1, $create = true, $write = false)
    {
        global $memory_permissions, $memory_starting_bytes;
        $bytes = max($memory_starting_bytes, $bytes);
        $block = @shmop_open($key, $write ? "w" : "a", $memory_permissions, $bytes);

        if (!$block && $create) {
            $block = shmop_open($key, "c", $memory_permissions, $bytes);

            if (!$block) {
                $this->throwException("Unable to open/read list-memory-block (" . $exceptionID . "): " . $key);
            }
        }
        return $block;
    }

    /**
     * @throws Exception
     */
    private function internalGetBlockSize($exceptionID, $key, $block): int
    {
        if (!$block) {
            return -1;
        }
        $size = shmop_size($block);

        if (!$size) {
            $this->throwException("Failed to read size of list-memory-block (" . $exceptionID . "): " . $key);
        }
        return $size;
    }

    /**
     * @throws Exception
     */
    private function throwException($exception, $destroy = true)
    {
        if ($destroy) {
            $this->internalDestroy();
        }
        throw new Exception($exception);
    }
}