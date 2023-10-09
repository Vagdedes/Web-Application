<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/default_sql.php';
require_once '/var/www/.structure/library/memory/init.php';
$administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";

function get_communication_private_key($hash = true): ?string
{
    $private_verification_key = get_keys_from_file("/var/www/.structure/private/private_verification_key", 1);
    return $private_verification_key === null ? null : ($hash ? hash("sha512", $private_verification_key[0]) : $private_verification_key[0]);
}

function private_file_get_contents($url, $createAndClose = false): bool|string
{
    return post_file_get_contents(
        $url,
        array(
            "private_verification_key" => get_communication_private_key(false),
            "private_ip_address" => get_client_ip_address()
        ),
        false,
        $_SERVER['HTTP_USER_AGENT'] ?? "",
        $createAndClose ? 1 : 0
    );
}

// Separator

function is_private_connection(): bool
{
    if (hash("sha512", ($_POST['private_verification_key'] ?? "")) === get_communication_private_key()) {
        global $administrator_local_server_ip_addresses_table;
        set_sql_cache();
        return !empty(get_sql_query(
            $administrator_local_server_ip_addresses_table,
            array("id"),
            array(
                array("ip_address", get_local_ip_address()),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null,
            ),
            null,
            1
        ));
    }
    return false;
}

function get_private_ip_address(): ?string
{
    return is_private_connection() ? ($_POST['private_ip_address'] ?? null) : null;
}

// Separator

function get_session_account_id()
{
    return is_private_connection() ? $_POST['session_account_id'] ?? null : null;
}

function set_session_account_id($id)
{
    $_POST['session_account_id'] = $id;
}

function has_session_account_id(): bool
{
    return !empty(get_session_account_id());
}