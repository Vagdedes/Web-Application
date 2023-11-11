<?php

function add_cookie($name, $info, $time): bool
{
    return setcookie($name, $info, [
        'expires' => time() + $time,
        'path' => '/',
        'domain' => "." . get_domain(false),
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function delete_cookie($name): bool
{
    return setcookie($name, "", [
        'expires' => time() - 1,
        'path' => '/',
        'domain' => "." . get_domain(false),
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function cookie_exists($name): bool
{
    return isset($_COOKIE[$name]);
}

function get_cookie($name)
{
    return $_COOKIE[$name] ?? null;
}

function set_cookie_to_value_if_not($name, $value, $time, bool $force = false): bool
{
    if (!cookie_exists($name)) {
        return add_cookie($name, $value, $time);
    } else if ($force || $_COOKIE[$name] != $value) {
        add_cookie($name, $value, $time);
    }
    return false;
}
