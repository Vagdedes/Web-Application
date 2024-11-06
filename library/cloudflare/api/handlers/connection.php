<?php

class CloudflareConnection
{

    public static function query(string $type, string $service, mixed $arguments = null): object|bool|null
    {
        $credentials = get_keys_from_file(CloudflareVariables::CLOUDFLARE_CREDENTIALS_DIRECTORY, 1);

        if ($credentials === null) {
            return null;
        }
        return @json_decode(get_curl(
            "https://api.cloudflare.com/client/" . CloudflareVariables::CLOUDFLARE_API_VERSION . "/" . $service,
            $type,
            array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $credentials[0]
            ),
            $arguments,
            5
        ), false);
    }

    public static function query_pages(string $type, string $service, mixed $arguments = null): array
    {
        $results = array();
        $object = self::query(
            $type,
            $service,
            $arguments
        );

        if (is_object($object)) {
            $results[] = $object;
        }
        if ($arguments !== null) {
            $arguments = json_decode($arguments);

            if ($arguments === null) {
                return $results;
            }
        } else {
            $arguments = new stdClass();
        }

        while ($object?->result_info?->page !== null) {
            $arguments->page = $object?->result_info?->page + 1;

            if ($arguments->page > $object?->result_info?->total_pages) {
                break;
            }
            $object = self::query(
                $type,
                $service,
                json_encode($arguments)
            );

            if (is_object($object)) {
                $results[] = $object;
            }
        }
        return $results;
    }

}