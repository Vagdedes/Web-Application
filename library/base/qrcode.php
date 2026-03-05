<?php

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

function generate_qr_code(
    string $text,
    string $outputType = QRCode::OUTPUT_IMAGE_PNG,
    bool   $base64 = true
): string
{
    $options = new QROptions([
        'version' => QRCode::VERSION_AUTO,
        'outputType' => $outputType,
        'eccLevel' => QRCode::ECC_L,
        'scale' => 5,
        'imageBase64' => $base64,
    ]);
    return (new QRCode($options))->render($text);
}