<?php

function encrypt_password(int|string|float $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function is_valid_password(string $encryptedPassword, int|string|float $storedPassword): bool
{
    return password_verify($encryptedPassword, $storedPassword);
}
