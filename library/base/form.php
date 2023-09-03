<?php
function get_form($str)
{
    return $_POST[$str] ?? ($_GET[$str] ?? "");
}

function get_form_post($str)
{
    return $_POST[$str] ?? "";
}

function get_form_get($str)
{
    return $_GET[$str] ?? "";
}

function has_form_post($str): bool
{
    return isset($_POST[$str]);
}

function has_form_get($str): bool
{
    return isset($_GET[$str]);
}
