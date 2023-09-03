<?php

function addRecord($domain, $type, $name, $content, $ttl, $priority, $proxied)
{
    $keys = get_keys_from_file("/var/www/.structure/private/cloudflare_credentials", 2);

    if ($keys === null) {
        return false;
    }
    $cloudflare_api_email = $keys[0];
    $cloudflare_api_key = $keys[1];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/" . getZone($domain)->result[0]->id . "/dns_records");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"type":"' . $type . '","name":"' . $name . '","content":"' . $content . '","ttl":' . $ttl . ',"priority":' . $priority . ',"proxied":' . $proxied . '}');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

    $headers = array();
    $headers[] = 'X-Auth-Email: ' . $cloudflare_api_email;
    $headers[] = 'X-Auth-Key: ' . $cloudflare_api_key;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}

function addSRVRecord($domain, $name, $target, $port, $protocol, $service)
{
    $keys = get_keys_from_file("/var/www/.structure/private/cloudflare_credentials", 2);

    if ($keys === null) {
        return false;
    }
    $cloudflare_api_email = $keys[0];
    $cloudflare_api_key = $keys[1];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/" . getZone($domain)->result[0]->id . "/dns_records");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{"type":"SRV","data":{"service":"_' . $service . '","proto":"_' . $protocol . '","name":"' . $name . '","priority":0,"weight":0,"port":' . $port . ',"target":"' . $target . '"}}');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

    $headers = array();
    $headers[] = 'X-Auth-Email: ' . $cloudflare_api_email;
    $headers[] = 'X-Auth-Key: ' . $cloudflare_api_key;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}

function getRecords($domain)
{
    $keys = get_keys_from_file("/var/www/.structure/private/cloudflare_credentials", 2);

    if ($keys === null) {
        return false;
    }
    $cloudflare_api_email = $keys[0];
    $cloudflare_api_key = $keys[1];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/" . getZone($domain)->result[0]->id . "/dns_records");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    $headers = array();
    $headers[] = 'X-Auth-Email: ' . $cloudflare_api_email;
    $headers[] = 'X-Auth-Key: ' . $cloudflare_api_key;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}

function getRecord($domain, $name)
{
    $keys = get_keys_from_file("/var/www/.structure/private/cloudflare_credentials", 2);

    if ($keys === null) {
        return null;
    }
    $cloudflare_api_email = $keys[0];
    $cloudflare_api_key = $keys[1];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/" . getZone($domain)->result[0]->id . "/dns_records?name=" . $name . "." . $domain);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    $headers = array();
    $headers[] = 'X-Auth-Email: ' . $cloudflare_api_email;
    $headers[] = 'X-Auth-Key: ' . $cloudflare_api_key;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result);
    return isset($json->result[0]) ? $json->result[0] : null;
}

function deleteRecord($domain, $name)
{
    $record = getRecord($domain, $name);

    if ($record == null) {
        return null;
    }
    $keys = get_keys_from_file("/var/www/.structure/private/cloudflare_credentials", 2);

    if ($keys === null) {
        return false;
    }
    $cloudflare_api_email = $keys[0];
    $cloudflare_api_key = $keys[1];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones/" . $record->zone_id . "/dns_records/" . $record->id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

    $headers = array();
    $headers[] = 'X-Auth-Email: ' . $cloudflare_api_email;
    $headers[] = 'X-Auth-Key: ' . $cloudflare_api_key;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}

function getZone($domain)
{
    $keys = get_keys_from_file("/var/www/.structure/private/cloudflare_credentials", 2);

    if ($keys === null) {
        return false;
    }
    $cloudflare_api_email = $keys[0];
    $cloudflare_api_key = $keys[1];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudflare.com/client/v4/zones?name=" . $domain);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

    $headers = array();
    $headers[] = 'X-Auth-Email: ' . $cloudflare_api_email;
    $headers[] = 'X-Auth-Key: ' . $cloudflare_api_key;
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}
