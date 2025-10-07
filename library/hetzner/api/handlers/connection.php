<?php

function get_hetzner_object(string $type, string $service, mixed $arguments = null): object|bool|null
{
    $credentials = get_keys_from_file(HetznerVariables::HETZNER_CREDENTIALS_DIRECTORY, 1);

    if ($credentials === null) {
        return null;
    }
    return json_decode(get_curl(
        "https://api.hetzner.cloud/" . HetznerVariables::HETZNER_API_VERSION . "/" . $service,
        $type,
        array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $credentials[0]
        ),
        $arguments,
        5
    ), false);
}

function get_hetzner_object_pages(string $type, string $service, mixed $arguments = null): array
{
    $results = array();
    $object = get_hetzner_object(
        $type,
        $service . "?page=1",
        $arguments
    );

    if (is_object($object)) {
        $results[] = $object;
    }
    if (isset($object->meta->pagination->next_page)) {
        while ($object?->meta?->pagination?->next_page !== null) {
            $object = get_hetzner_object(
                $type,
                $service . "?page=" . urlencode($object?->meta?->pagination?->next_page),
                $arguments
            );

            if (is_object($object)) {
                $results[] = $object;
            }
        }
    }
    return $results;
}
