<?php
function get_form(int|string $str, bool $null = true): mixed
{
    return $_POST[$str] ?? ($_GET[$str] ?? ($null ? null : ""));
}

function get_form_post(int|string $str): mixed
{
    return $_POST[$str] ?? "";
}

function get_form_get(int|string $str): mixed
{
    return $_GET[$str] ?? "";
}

function has_form_post(int|string $str): bool
{
    return isset($_POST[$str]);
}

function has_form_get(int|string $str): bool
{
    return isset($_GET[$str]);
}
