<?php

function get_hetzner_object(string $service): object|bool|null
{
    $credentials = get_keys_from_file(HetznerVariables::HETZNER_CREDENTIALS_DIRECTORY, 1);

    if ($credentials === null) {
        return null;
    }
    return @json_decode(get_curl(
        "https://api.hetzner.cloud/" . HetznerVariables::HETZNER_API_VERSION . "/" . $service,
        "GET",
        array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $credentials[0]
        ),
        null,
        5
    ), false);
}

function get_hetzner_object_pages(string $service): array
{
    $results = array();
    $object = get_hetzner_object($service . "?page=1");

    if (is_object($object)) {
        $results[] = $object;
    }
    while ($object?->meta?->pagination?->next_page !== null) {
        $object = get_hetzner_object($service . "?page=" . $object?->meta?->pagination?->next_page);

        if (is_object($object)) {
            $results[] = $object;
        }
    }
    return $results;
}
