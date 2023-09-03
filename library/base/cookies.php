<?php
function add_cookie($name, $info, $time): bool
{
    return setcookie($name, $info, time() + $time, '/');
}

function delete_cookie($name): bool
{
    return setcookie($name, "", time() - 1, '/');
}

function cookie_exists($name): bool
{
    return isset($_COOKIE[$name]);
}

function get_cookie($name)
{
    return $_COOKIE[$name] ?? null;
}
