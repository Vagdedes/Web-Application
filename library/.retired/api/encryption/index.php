<?php
$apiAddress = "";
$apiVersion = "";
$key1 = "";
$key2 = "";
$apiSeparator = " ";

function encryptIP_BACKUP($data) {
    global $apiAddress, $apiVersion, $key1, $key2;
    $contents = @file_get_contents("http://" . $apiAddress . "/" . $apiVersion . "/?value=" . $data . "&key1=" . $key1 . "&key2=" . $key2 . "&action=encrypt");

    if ($contents !== false) {
        return $contents;
    }
    return $data;
}

function encryptIPs_BACKUP($data) {
    global $apiAddress, $apiVersion, $apiSeparator, $key1, $key2;
    $contents = @file_get_contents("http://" . $apiAddress . "/" . $apiVersion . "/?values=" . rawurlencode($data) . "&key1=" . $key1 . "&key2=" . $key2 . "&action=encrypt");

    if ($contents !== false) {
        return explode($apiSeparator, $contents);
    }
    return $data;
}

function decryptIP_BACKUP($data) {
    global $apiAddress, $apiVersion, $key1, $key2;
    $contents = @file_get_contents("http://" . $apiAddress . "/" . $apiVersion . "/?value=" . $data . "&key1=" . $key1 . "&key2=" . $key2 . "&action=decrypt");

    if ($contents !== false) {
        return $contents;
    }
    return $data;
}

function decryptIPs_BACKUP($data) {
    global $apiAddress, $apiVersion, $apiSeparator, $key1, $key2;
    $contents = @file_get_contents("http://" . $apiAddress . "/" . $apiVersion . "/?values=" . rawurlencode($data) . "&key1=" . $key1 . "&key2=" . $key2 . "&action=decrypt");

    if ($contents !== false) {
        return explode($apiSeparator, $contents);
    }
    return $data;
}

/*$multiplier = 5637918024;

function encryptIP($data) {
    $ip2long = ip2long($data);

    if ($ip2long !== false) {
        global $multiplier;
        return $ip2long * $multiplier;
    }
    return $data;
}

function decryptIP($data) {
    if (is_numeric($data)) {
        global $multiplier;
        return long2ip(round($data / $multiplier));
    }
    return $data;
}*/
