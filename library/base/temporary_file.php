<?php

function create_temporary_file(string $contents): ?string
{
    $path = tempnam(sys_get_temp_dir(), random_number(9));

    if (!@file_put_contents($path, $contents)) {
        return null;
    }
    $verify = timed_file_get_contents($path, 3);

    if ($verify === false) {
        return null;
    }
    return $path;
}
