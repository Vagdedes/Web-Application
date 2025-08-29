<?php
function get_form(int|string $str, mixed $default = null): mixed
{
    return trim($_POST[$str] ?? ($_GET[$str] ?? $default));
}

function get_form_post(int|string $str, mixed $default = ""): mixed
{
    return trim($_POST[$str] ?? $default);
}

function get_form_get(int|string $str, mixed $default = ""): mixed
{
    return trim($_GET[$str] ?? $default);
}

function has_form_post(int|string $str): bool
{
    return isset($_POST[$str]);
}

function has_form_get(int|string $str): bool
{
    return isset($_GET[$str]);
}
