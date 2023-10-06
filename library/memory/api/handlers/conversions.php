<?php

function manipulate_memory_key($key): bool|string
{
    global $memory_serialize_key;

    if ($memory_serialize_key) {
        return $key === null ? false : serialize(is_object($key) ? get_object_vars($key) : $key);
    } else {
        $memory_serialize_key = true;
        return $key;
    }
}

function manipulate_memory_date($cooldown, $maxTime = 86400)
{
    if ($cooldown === null) {
        return false;
    }
    if (is_array($cooldown)) {
        $cooldown = strtotime("+" . implode(" ", $cooldown));

        if ($cooldown === false) {
            return null;
        }
    } else if (is_numeric($cooldown)) {
        $cooldown = time() + min($cooldown, $maxTime);
    } else {
        $cooldown = strtotime("+" . $cooldown);

        if ($cooldown === false) {
            return null;
        }
    }
    return min($cooldown, time() + $maxTime);
}
