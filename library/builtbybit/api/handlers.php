<?php
function get_builtbybit_wrapper(): mixed
{
    $keys = get_keys_from_file("/var/www/.structure/private/builtbybit_credentials", 2);

    if ($keys === null) {
        return null;
    }
    $token = new APIToken(TokenType::PRIVATE, $keys[0]);
    $wrapper = new APIWrapper();
    $response = $wrapper->initialise($token, true);
    return $response->isSuccess() ? $wrapper : $response->getError()["message"];
}

function get_builtbybit_resource_ownerships(int|string $resource): array
{
    $cacheKey = array(
        $resource,
        "builtbybit-resource-ownerships"
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_array($cache)) {
        //return $cache;
    }
    $wrapper = get_builtbybit_wrapper();

    if (is_object($wrapper)) {
        $array = $wrapper->resources()->licenses()->list($resource)->getData();

        if (is_array($array)) {
            if (!empty($array)) {
                foreach ($array as $key => $subArray) {
                    if (array_key_exists("purchaser_id", $subArray)
                        && array_key_exists("validated", $subArray)
                        && array_key_exists("active", $subArray)
                        && array_key_exists("license_id", $subArray)
                        && array_key_exists("start_date", $subArray)
                        && array_key_exists("end_date", $subArray)
                        && $subArray["validated"] === true) {
                        $object = new stdClass();
                        $object->user = $subArray["purchaser_id"];
                        $object->active = $subArray["active"] === true;
                        $object->transaction_id = "builtbybit_" . $subArray["license_id"];
                        $object->creation_date = time_to_date($subArray["start_date"]);
                        $endDate = $subArray["end_date"];
                        $object->expiration_date = $endDate == 0 ? null : time_to_date($endDate);
                        $array[$object->user] = $object;
                    }
                    unset($array[$key]);
                }
            }
        } else {
            $array = array();
        }
    } else {
        $array = array();
    }
    set_key_value_pair($cacheKey, $array, "1 minute");
    return $array;
}

function get_builtbybit_individual_resource_ownership(int|string $resource, int|string $user): ?object
{
    $ownerships = get_builtbybit_resource_ownerships($resource);
    return array_key_exists($user, $ownerships) ? $ownerships[$user] : null;
}