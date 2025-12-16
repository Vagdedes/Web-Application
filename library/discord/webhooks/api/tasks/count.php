<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/memory/init.php';

$id = get_form("id", 289384242075533313);
$cacheKey = array(
    "discord-live-member-count",
    $id
);
$cache = get_key_value_pair($cacheKey, 0); // Normally we don't use a redundancy value here, but due to Discord limits we do

if (is_numeric($cache)) {
    echo $cache;
} else if (is_numeric($id)) {
    $contents = timed_file_get_contents('https://discordapp.com/api/guilds/' . $id . '/widget.json', 3);
    $count = 0;

    if ($contents !== false) {
        $object = json_decode($contents);

        if (is_object($object) && isset($object->presence_count)) {
            $number = $object->presence_count;

            if (is_numeric($number)) {
                $count = $number;
            }
        }
    }
    set_key_value_pair($cacheKey, $count, "1 hour");
    echo $count;
} else {
    echo "0";
}