<?php
function get_builtbybit_wrapper(): mixed
{
    $keys = get_keys_from_file(BuiltByBitVariables::CREDENTIALS_DIRECTORY, 2);

    if ($keys === null) {
        return null;
    }
    $token = new APIToken(TokenType::PRIVATE, $keys[0]);
    $wrapper = new APIWrapper();
    $response = $wrapper->initialise($token, true);
    return $response->isSuccess() ? $wrapper : $response->getError()["message"];
}

function get_builtbybit_resource_ownerships(int|string $resource, array $sort = []): array
{
    if (is_numeric($resource)) {
        $resource = (int)$resource;
    } else {
        return array();
    }
    $cacheKey = array(
        $resource,
        "builtbybit-resource-ownerships",
        array_to_integer($sort)
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_array($cache)
        && !empty($cache)) {
        return $cache;
    }
    $wrapper = get_builtbybit_wrapper();

    if (is_object($wrapper)) {
        $array = $wrapper->resources()->licenses()->list($resource, $sort)->getData();

        if (is_array($array)) {
            if (!empty($array)) {
                $final = array();

                foreach ($array as $subArray) {
                    if (array_key_exists("purchaser_id", $subArray)
                        && array_key_exists("active", $subArray)
                        && array_key_exists("license_id", $subArray)
                        && array_key_exists("start_date", $subArray)
                        && array_key_exists("end_date", $subArray)
                        && ($subArray["validated"] ?? false) === true) {
                        $object = new stdClass();
                        $object->user = $subArray["purchaser_id"];
                        $object->active = $subArray["active"] === true;
                        $object->transaction_id = "builtbybit_" . $subArray["license_id"];
                        $object->creation_date = time_to_date($subArray["start_date"]);
                        $endDate = $subArray["end_date"];
                        $object->expiration_date = $endDate == 0
                            ? null
                            : time_to_date($endDate);
                        $final[$object->user] = $object;
                    }
                }
                $array = $final;
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

function has_builtbybit_resource_ownership(int|string $resource, int|string $member): bool
{
    $cacheKey = array(
        $resource,
        $member,
        "builtbybit-resource-ownership"
    );
    $page = 1;

    while (true) {
        $ownerships = get_builtbybit_resource_ownerships($resource, array("page" => $page));

        if (empty($ownerships)) {
            break;
        } else {
            $object = $ownerships[$member] ?? null;

            if ($object !== null) {
                $bool = $object->active
                    && ($object->expiration_date === null
                        || $object->expiration_date > time_to_date(time()));
                set_key_value_pair($cacheKey, $bool, "1 minute");
                return $bool;
            }
            $page++;
        }
    }
    set_key_value_pair($cacheKey, false, "1 minute");
    return false;
}