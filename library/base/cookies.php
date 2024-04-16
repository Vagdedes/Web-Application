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

function delete_all_cookies(): bool
{
    if (!empty($_COOKIE)) {
        foreach ($_COOKIE as $key => $value) {
            setcookie($key, $value, 0, '/');
        }
        return true;
    } else {
        return false;
    }
}

function cookie_exists(int|string $name): bool
{
    return isset($_COOKIE[$name]);
}

function get_cookie(int|string $name)
{
    return $_COOKIE[$name] ?? null;
}
