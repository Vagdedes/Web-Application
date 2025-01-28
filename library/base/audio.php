<?php

function convert_base64_ogg_to_base64_mp3(string $base64): ?string
{
    // 1.
    $oldData = @base64_decode($base64);

    if ($oldData === false) {
        return null;
    }
    // 2.
    $oldFile = tempnam(sys_get_temp_dir(), 'ogg');
    file_put_contents($oldFile, $oldData);
    // 3.
    $newFile = tempnam(sys_get_temp_dir(), 'mp3');
    // 4.
    shell_exec("ffmpeg -y -i " . escapeshellarg($oldFile) . " -f mp3 " . escapeshellarg($newFile));
    // 5.
    $newData = file_get_contents($newFile);

    if ($newData === false) {
        return null;
    }
    // 6.
    $base64 = @base64_encode($newData);
    // 7.
    unlink($oldFile);
    unlink($newFile);
    return $base64;
}

function convert_base64_ogg_to_base64_wav(string $base64): ?string
{
    // 1.
    $oldData = @base64_decode($base64);

    if ($oldData === false) {
        return null;
    }
    // 2.
    $oldFile = tempnam(sys_get_temp_dir(), 'ogg');
    file_put_contents($oldFile, $oldData);
    // 3.
    $newFile = tempnam(sys_get_temp_dir(), 'wav');
    // 4.
    shell_exec("ffmpeg -y -i " . escapeshellarg($oldFile) . " -acodec pcm_s16le -ac 2 -ar 44100 " . escapeshellarg($newFile));
    // 5.
    $newData = file_get_contents($newFile);

    if ($newData === false) {
        return null;
    }
    // 6.
    $base64 = @base64_encode($newData);
    // 7.
    unlink($oldFile);
    unlink($newFile);
    return $base64;
}
