<?php

function get_polymart_object(string $version, string $service, array $parameters): ?object
{
    $cacheKey = array(
        $service,
        $parameters,
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
    global $polymart_object_refreshTime;
    $parameters["api_key"] = $polymart_credentials[0];
    $json = get_json_object(
        "https://api.polymart.org/" . $version . "/" . $service . "/",
        $parameters,
        3
    );

    if ($json === false
        || !isset($json->response->success)
        || !$json->response->success) {
        set_key_value_pair($cacheKey, false, $polymart_object_refreshTime);
        return null;
    }
    set_key_value_pair($cacheKey, $json, $polymart_object_refreshTime);
    return $json;
}

function get_polymart_buyers(int $productID): ?array
{
    $object = get_polymart_object(
        "v1",
        "listBuyers",
        array("resource_id" => $productID)
    );

    if (is_object($object) && isset($object->response->buyers)) {
        return $object->response->buyers;
    } else {
        return array();
    }
}
function get_polymart_buyer_details(int $productID, int $userID): ?object
{
    $object = get_polymart_object(
        "v1",
        "listBuyers",
        array("resource_id" => $productID)
    );

    if (is_object($object) && isset($object->response->buyers)) {
        foreach ($object->response->buyers as $buyer) {
            if ($buyer->userID === $userID) {
                return $buyer;
            }
        }
    }
    return null;
}
