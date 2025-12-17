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
    $memoryBlock = new IndividualMemoryBlock(IndividualMemoryBlock::MEMORY_RESERVED_KEYS[0]);
    $array = $memoryBlock->get();

    if (is_array($array)) {
        return $array;
    } else {
        //$stringToFix = "echo 32768 >/proc/sys/kernel/shmmni";
        //$oldCommand = "ipcs -m | grep 'www-data.*$memory_permissions_string'";
        $array = array();
        $memoryBlock->set($array, false, false, true); // Prevent redundancy without data
        $query = explode(chr(32), shell_exec("ipcs -m"));

        if (!empty($query)) {
            $size = 0;

            foreach ($query as $value) {
                $char = $value[0] ?? null;

                if ($char !== null
                    && $char !== "w"
                    && !is_numeric($value)) {
                    $array[] = @hexdec($value);
                    $size++;
                }
            }
            $memoryBlock->set($array, false, false, true); // Prevent redundancy with data

            // Separator

            $memoryDifferenceBlock = new IndividualMemoryBlock(IndividualMemoryBlock::MEMORY_RESERVED_KEYS[1]);
            $difference = $memoryDifferenceBlock->get();

            if (is_numeric($difference)) {
                $difference = $size - $difference;

                if ($difference >= 200) {
                    clear_memory_segments($array);

                    if ($memoryDifferenceBlock->delete(true)) { // Clears segment ID cache
                        return get_memory_segment_ids();
                    }
                } else if ($difference < 0) { // Correct in case
                    $memoryDifferenceBlock->set($size, false, false, true);
                }
            } else {
                $memoryDifferenceBlock->set($size, false, false, true);
            }
        } else {
            $memoryBlock->set($array, false, false, true);
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

            if ($isNull) {
                continue;
            }
            if (isset($object->invalid)) {
                if ($memoryBlock->delete(false, false)
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
                if ($memoryBlock->delete(false, false)) {
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

    public const
        MEMORY_RESERVED_KEYS = array(
        0 => 0xff1, // Reserved for caching memory segment ids
        1 => 0xff2 // Reserved for caching memory segment id difference
    );

    private const
        MEMORY_PERMISSIONS = 0644,
        MEMORY_STARTING_BYTES = 2;

    private mixed $originalKey;
    private int $key;
    private bool $modify;

    public function __construct(mixed $key)
    {
        $this->originalKey = $key;
        $this->key = is_integer($key)
            ? $key
            : (is_object($key) || is_array($key)
                ? array_to_integer($key)
                : string_to_integer($key));
        $this->modify = !in_array($this->key, self::MEMORY_RESERVED_KEYS);
    }

    public function getObject(): ?object
    {
        $block = $this->getBlock();

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

    public function delete(bool $force = false, bool $clearCache = true): bool
    {
        if ($this->modify || $force) {
            $block = $this->getBlock();

            if ($block && shmop_delete($block)) {
                if ($clearCache) {
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

    public function set(mixed $value, int|string|null|bool $expiration = false,
                        bool  $retry = true, bool $force = false): bool
    {
        if (!$force && !$this->modify) {
            return false;
        }
        $object = new stdClass();
        $object->key = $this->originalKey;
        $object->value = $value;
        $object->expiration = is_numeric($expiration) ? $expiration : strtotime(get_future_date("1 hour"));
        $object->creation = time();
        $objectToText = @gzdeflate(serialize($object), 9);

        if ($objectToText === false) {
            return false;
        }
        $objectToTextLength = strlen($objectToText);
        $block = $this->getBlock(true);
        $bytesSize = $this->getBlockSize($block); // check default

        if (!$block) {
            $bytesSize = max($objectToTextLength, self::MEMORY_STARTING_BYTES);
            $block = $this->createBlock($bytesSize); // open default

            if (!$block) {
                if ($retry) {
                    clear_memory_segments(get_memory_segment_ids(), 1);

                    if ($this->set($value, $expiration, false)) {
                        return true;
                    } else {
                        $this->clearSegmentsIdCache();
                    }
                } else {
                    return false;
                }
            } else {
                $this->clearSegmentsIdCache();
            }
        } else if ($objectToTextLength > $bytesSize) {
            $bytesSize = $objectToTextLength;

            if (!shmop_delete($block)) {
                return false;
            }
            $block = $this->createBlock($bytesSize); // open bigger

            if (!$block) {
                return false;
            }
        } else if ($objectToTextLength < $bytesSize) {
            $bytesSize = max($objectToTextLength, self::MEMORY_STARTING_BYTES);

            if (!shmop_delete($block)) {
                return false;
            }
            $block = $this->createBlock($bytesSize); // open smaller

            if (!$block) {
                return false;
            }
        }
        if (is_object($block)) {
            $bytesWritten = shmop_write($block, $objectToText, 0);
            return $bytesWritten === $bytesSize;
        } else {
            return false;
        }
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
        if ($this->key !== self::MEMORY_RESERVED_KEYS[0]) {
            $memoryBlock = new IndividualMemoryBlock(self::MEMORY_RESERVED_KEYS[0]);
            $block = $memoryBlock->getBlock();

            if ($block) {
                shmop_delete($block);
            }
        }
    }

    private function getBlock(bool $write = false): mixed
    {
        return @shmop_open($this->key, $write ? "w" : "a", 0, 0);
    }

    private function createBlock(int $bytes): mixed
    {
        return @shmop_open($this->key, "c", self::MEMORY_PERMISSIONS, $bytes);
    }

    private function getBlockSize(mixed $block): int
    {
        if (!$block) {
            return self::MEMORY_STARTING_BYTES;
        } else {
            $size = shmop_size($block);

            if (!$size) {
                return self::MEMORY_STARTING_BYTES;
            } else {
                return $size;
            }
        }
    }
}