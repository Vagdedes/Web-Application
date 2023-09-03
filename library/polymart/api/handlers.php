<?php

function get_polymart_object($service, $parameters, $longCache = true): ?object
{
    $cacheKey = array(
        $service,
        $parameters,
        $longCache,
        "polymart-object"
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_object($cache)) {
        return $cache;
    } else if ($cache === false) {
        return null;
    }
    $polymart_credentials = get_keys_from_file("/var/www/.structure/private/polymart_credentials", 1);

    if ($polymart_credentials === null) {
        return null;
    }
    global $polymart_object_LongRefreshTime, $polymart_object_ShortRefreshTime;
    $longCache = $longCache ? $polymart_object_LongRefreshTime :
        $polymart_object_ShortRefreshTime;
    $parameters["api_key"] = $polymart_credentials[0];
    $json = get_json_object("https://api.polymart.org/v1/" . $service . "/", $parameters);

    if ($json === false
        || !isset($json->response->success)
        || !$json->response->success) {
        set_key_value_pair($cacheKey, false, $longCache);
        return null;
    }
    set_key_value_pair($cacheKey, $json, $longCache);
    return $json;
}
