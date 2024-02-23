<?php

function get_memory_segment_limit(): int
{
    $niddle = "max number of segments = ";
    return substr(
        shell_exec("ipcs -l | grep '$niddle'"),
        strlen($niddle),
        -1
    );
}

function get_memory_segment_ids(): array
{
    global $memory_reserved_keys;
    $memoryBlock = new IndividualMemoryBlock($memory_reserved_keys[0]);
    $array = $memoryBlock->get();

    if (is_array($array)) {
        return $array;
    } else {
        //$stringToFix = "echo 32768 >/proc/sys/kernel/shmmni";
        //$oldCommand = "ipcs -m | grep 'www-data.*$memory_permissions_string'";
        $array = explode(chr(32), shell_exec("ipcs -m"));
        $current = 0;

        if (!empty($array)) {
            foreach ($array as $key => $value) {
                if (empty($value) || is_numeric($value) || $value[0] === "w") {
                    unset($array[$key]);
                } else {
                    $array[$key] = @hexdec($value);
                    $current++;
                }
            }
        }

        // Separator
        $memoryDifferenceBlock = new IndividualMemoryBlock($memory_reserved_keys[1]);
        $difference = $memoryDifferenceBlock->get();

        if (is_numeric($difference)) {
            $difference = $current - $difference;

            if ($difference >= 250) {
                clear_memory_segments($array);
                $memoryDifferenceBlock->delete(true);
            } else {
                $memoryBlock->set($array);

                if ($difference < 0) {
                    $memoryDifferenceBlock->set($current, false, false, true);
                }
            }
        } else {
            $memoryBlock->set($array, false, true, true);
            $memoryDifferenceBlock->set($current, false, false, true);
        }
        return $array;
    }
}

function clear_memory_segments(array $segments, int $deleteBlocksRegardless = 0): void
{
    if (!empty($segments)) {
        $sortedByCreation = array();
        $deleteBlocks = $deleteBlocksRegardless > 0;

        foreach ($segments as $segment) {
            $memoryBlock = new IndividualMemoryBlock($segment);
            $object = $memoryBlock->getObject();
            $isNull = $object === null;

            if ($isNull || isset($object->invalid)) {
                if (!$isNull
                    && $memoryBlock->delete(true)
                    && $deleteBlocks) {
                    $deleteBlocksRegardless--;

                    if ($deleteBlocksRegardless == 0) {
                        return;
                    }
                }
            } else if ($deleteBlocks) {
                $sortedByCreation[$object->creation] = $memoryBlock;
            }
        }

        if (!empty($sortedByCreation)) {
            ksort($sortedByCreation); // Sort in ascending order, so we start from the least recent

            foreach ($sortedByCreation as $memoryBlock) {
                if ($memoryBlock->delete()) {
                    $deleteBlocksRegardless--;

                    if ($deleteBlocksRegardless == 0) {
                        return;
                    }
                }
            }
        }
    }
}

// Separator

class IndividualMemoryBlock
{
    private mixed $originalKey;
    private int $key;
    private bool $modify;

    public function __construct(mixed $key)
    {
        global $memory_reserved_keys;

        if (is_integer($key)) { // Used for reserved or existing keys
            $this->originalKey = $key;
            $this->key = $key;
        } else {
            $this->originalKey = $key;
            $this->key = string_to_integer($key);
        }
        $this->modify = !in_array($this->key, $memory_reserved_keys);
    }

    public function getObject(): ?object
    {
        global $memory_starting_bytes;
        $block = $this->getBlock($memory_starting_bytes, false);

        if (!$block) {
            return null;
        }
        $readMapBytes = @shmop_read(
            $block,
            0,
            $this->getBlockSize($block)
        );

        if (!$readMapBytes) {
            return $this->invalid(1);
        }
        $value = @gzinflate($readMapBytes);

        if ($value !== false) {
            $object = @unserialize($value);

            if (isset($object->expiration)
                && ($object->expiration === false || $object->expiration >= time())) {
                return $object;
            } else {
                $object->invalid = 2;
                return $object;
            }
        }
        return $this->invalid(3);
    }

    public function get(string $objectKey = "value")
    {
        $object = $this->getObject();
        return $object !== null && !isset($object->invalid)
            ? $object?->{$objectKey}
            : null;
    }

    public function exists(): bool
    {
        $object = $this->getObject();
        return $object !== null && !isset($object->invalid);
    }

    // Separator

    public function delete(bool $force = false): bool
    {
        if ($this->modify || $force) {
            global $memory_starting_bytes;
            $block = $this->getBlock($memory_starting_bytes, false);

            if ($this->deleteBlock($block, false)) {
                global $memory_reserved_keys;

                if ($this->key !== $memory_reserved_keys[0]) {
                    $this->clearSegmentsIdCache();
                }
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    // Separator

    public function set(mixed $value, int|string|null|bool $expiration = false,
                        bool  $retry = true, bool $force = false): bool
    {
        if (!$force && !$this->modify) {
            return false;
        }
        global $memory_starting_bytes;

        $object = new stdClass();
        $object->key = $this->originalKey;
        $object->value = $value;
        $object->expiration = is_numeric($expiration) ? $expiration : false;
        $object->creation = time();
        $objectToText = @gzdeflate(serialize($object), 9);

        if ($objectToText === false) {
            return false;
        }
        $objectToTextLength = strlen($objectToText);
        $block = $this->getBlock($memory_starting_bytes, false, true);
        $bytesSize = $this->getBlockSize($block); // check default

        if (!$block) {
            $bytesSize = max($objectToTextLength, $memory_starting_bytes);
            $block = $this->getBlock($bytesSize, true, true); // open default

            if (!$block) {
                if ($retry) {
                    clear_memory_segments(get_memory_segment_ids(), 1);
                    return $this->set($value, $expiration, false);
                } else {
                    return false;
                }
            } else {
                global $memory_reserved_keys;

                if ($this->key !== $memory_reserved_keys[0]) {
                    $this->clearSegmentsIdCache();
                }
            }
        } else if ($objectToTextLength > $bytesSize) {
            $bytesSize = $objectToTextLength;

            if (!$this->deleteBlock($block)) {
                return false;
            }
            $block = $this->getBlock($bytesSize, true, true); // open bigger

            if (!$block) {
                return false;
            }
        } else if ($objectToTextLength < $bytesSize) {
            $bytesSize = max($objectToTextLength, $memory_starting_bytes);

            if (!$this->deleteBlock($block)) {
                return false;
            }
            $block = $this->getBlock($bytesSize, true, true); // open smaller

            if (!$block) {
                return false;
            }
        }
        $bytesWritten = shmop_write($block, $objectToText, 0);
        return $bytesWritten === $bytesSize;
    }

    // Separator

    private function invalid(int $type): object
    {
        $object = new stdClass();
        $object->invalid = $type;
        return $object;
    }

    private function clearSegmentsIdCache(): void
    {
        global $memory_reserved_keys;
        $memoryBlock = new IndividualMemoryBlock($memory_reserved_keys[0]);
        $memoryBlock->delete(true);
    }

    // Separator

    private function deleteBlock(mixed $block, bool $returnOnEmpty = true): bool
    {
        return $block ? shmop_delete($block) : $returnOnEmpty;
    }

    private function getBlock(int $bytes, bool $create = true, bool $write = false): mixed
    {
        global $memory_permissions;
        $block = @shmop_open($this->key, $write ? "w" : "a", $memory_permissions, $bytes);

        if (!$block && $create) {
            return @shmop_open($this->key, "c", $memory_permissions, $bytes);
        } else {
            return $block;
        }
    }

    private function getBlockSize(mixed $block): int
    {
        if (!$block) {
            global $memory_starting_bytes;
            return $memory_starting_bytes;
        } else {
            $size = shmop_size($block);

            if (!$size) {
                global $memory_starting_bytes;
                return $memory_starting_bytes;
            } else {
                return $size;
            }
        }
    }
}