<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/memory/init.php';

$cacheKey = "discord-live-member-count";
$cache = get_key_value_pair($cacheKey, 0); // Normally we don't use a redundancy value here, but due to Discord limits we do

if (is_numeric($cache)) {
    echo $cache;
} else {
    $contents = timed_file_get_contents('https://discordapp.com/api/guilds/289384242075533313/widget.json', 3);
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
    set_key_value_pair($cacheKey, $count, $websiteRefreshRate);
    echo $count;
}