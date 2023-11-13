<?php

function add_cookie(int|string $name, mixed $value, int|string $time): bool
{
    return setcookie($name, $value, [
        'expires' => time() + $time,
        'path' => '/',
        'domain' => "." . get_domain(false),
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function delete_cookie(int|string $name): bool
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

function cookie_exists(int|string $name): bool
{
    return isset($_COOKIE[$name]);
}

function get_cookie(int|string $name)
{
    return $_COOKIE[$name] ?? null;
}

function set_cookie_to_value_if_not(int|string $name, mixed $value,
                                    int|string $time, bool $force = false): bool
{
    if (!cookie_exists($name)) {
        return add_cookie($name, $value, $time);
    } else if ($force || $_COOKIE[$name] != $value) {
        add_cookie($name, $value, $time);
    }
    return false;
}
