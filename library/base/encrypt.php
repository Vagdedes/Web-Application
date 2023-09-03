<?php
$multiplier = null;
$serializer = null;
$encryption_keys = null;
$characters = "abcdefghijklmnopqrstuvwxyz"; // Never use (, ), {, }, [, ], <, >, -, +, =, ', ", ., \, /, |, comma
$specificNumberDivisor = 3;
$counterDivisor = 2;
$counterModulo = 2;

// Loader

function load_encryption_keys()
{
    global $multiplier, $serializer;

    if ($multiplier === null && $serializer === null) {
        $encryption_keys = get_keys_from_file("/var/www/.structure/private/encryption_keys", 4);

        if ($encryption_keys === null) {
            echo "encryption failure";
            return;
        }
        $multiplier = word_to_number($encryption_keys[0], $encryption_keys[1]);
        $serializer = word_to_number($encryption_keys[2], $encryption_keys[3]);
    }
}

// Translation

function text_to_single_number($text)
{ // Helps translate single text to a number for more versatility in the algorithm. Also, counts as slight manipulation.
    $number = abs(is_numeric($text) ? $text : ord($text));

    if ($number <= 9) {
        return $number;
    } else {
        $number = (string)$number;
        $count = strlen($number);
        $summary = 0;

        for ($i = 0; $i < $count; $i++) {
            $summary += (int)$number[$i];
        }
        return max(round($summary / $count), 1);
    }
}

function word_to_number($word1, $word2)
{ // Helps translate word/s to a number for more versatility in the algorithm.
    $number1 = "";
    $number2 = "";
    $length1 = strlen($word1);
    $length2 = strlen($word2);

    for ($i = 0; $i < $length1; $i++) {
        $number1 .= text_to_single_number($word1[$i]);
    }
    for ($i = 0; $i < $length2; $i++) {
        $number2 .= text_to_single_number($word2[$i]);
    }
    return (double)(($length1 < $length2 ? "-" : "") . ($number1 . $number2));
}

function lower_case($string): string
{ // Helps put the string in lower case without modifying the exponent characters.
    $exponent = strpos($string, "E+");

    if ($exponent === false || (strlen($string) - $exponent) > 5) { // Doesn't have exponent (E+Integer, Integer length being between 1-3)
        return strtolower($string);
    }
    $string = strtolower($string);
    $string[$exponent] = "E";
    return $string;
}

// Utilities

function is_valid_character($string): bool
{ // Helps exclude important characters from being modified.
    return $string != "-"
        && $string != "+"
        && $string !== "."
        && $string !== "E"
        && $string === strtolower($string);
}

// Manipulation

// PHP Native Method/s:
// strrev: Reverses a string.

function base64_fast_encode($string): string
{ // Saves byte/s of data, and potentially hides the base64 encoding.
    return str_replace("=", "", base64_encode($string));
}

function exchange_cases($string): string
{ // Changes lower case to higher case, and the opposite.
    $result = "";

    for ($i = 0; $i < strlen($string); $i++) {
        $text = $string[$i];

        if (is_numeric($text)) {
            $result .= $text;
        } else {
            $lowerCaseText = strtolower($text);

            if ($text === $lowerCaseText) {
                $result .= strtoupper($text);
            } else {
                $result .= $lowerCaseText;
            }
        }
    }
    return $result;
}

function case_math($decimal, $case): int
{ // Manipulates a decimal based on specific cases.
    switch ($case) {
        case 1:
            return (int)ceil($decimal);
        case 0:
            return (int)round($decimal);
        case -1:
            return (int)floor($decimal);
        default:
            return 0;
    }
}

// Runnables

function background_encrypt_ip($rawText)
{
    $ip2long = ip2long($rawText);

    if ($ip2long !== false) {
        global $multiplier, $serializer, $characters, $specificNumberDivisor, $counterDivisor, $counterModulo;
        load_encryption_keys();
        $encryptedNumber = (string)(($ip2long * $serializer) / $multiplier);
        $encryptedText = "";

        for ($i = 0; $i < strlen($encryptedNumber); $i++) {
            $text = $encryptedNumber[$i];

            if (is_numeric($text)) { // Characters such as negative, positive, exponents & decimal points are exempted.
                $specificNumber = ($i % $specificNumberDivisor) !== 0;
                $modulo = $i % $counterModulo;
                $counter = case_math($i / $counterDivisor, $specificNumber ? $modulo : -$modulo);
                $letter = $characters[$text + $modulo + $counter]; // 26(-1) is the maximum it can handle
                $encryptedText .= $specificNumber ? strtoupper($letter) : $letter;
            } else {
                $encryptedText .= $text;
            }
        }
        return exchange_cases(strrev(base64_fast_encode($encryptedText)));
    }
    return $rawText;
}

function background_decrypt_ip($encryptedText)
{
    global $multiplier, $serializer, $characters;
    load_encryption_keys();
    $decodedEncryptedText = base64_decode(strrev(exchange_cases($encryptedText)));
    $encryptedTextCopy = lower_case($decodedEncryptedText);

    if ($decodedEncryptedText !== $encryptedTextCopy) { // Prevents collisions with identical lower case, higher case, or combination of both cases.
        global $specificNumberDivisor, $counterDivisor, $counterModulo;
        $decryptedNumber = "";

        for ($i = 0; $i < strlen($encryptedTextCopy); $i++) {
            $text = $encryptedTextCopy[$i];

            if (is_valid_character($text)) { // Characters such as negative, positive, exponents & decimal points are exempted.
                $specificNumber = ($i % $specificNumberDivisor) !== 0;
                $modulo = $i % $counterModulo;
                $counter = case_math($i / $counterDivisor, $specificNumber ? $modulo : -$modulo);
                $decryptedNumber .= strpos($characters, $text) - $modulo - $counter;
            } else {
                $decryptedNumber .= $text;
            }
        }

        if (is_numeric($decryptedNumber)) { // Checks if the decryption returned a number.
            return long2ip(round(($decryptedNumber * $multiplier) / $serializer));
        }
    }
    return $encryptedText;
}

// Methods

function encrypt_ip($data)
{
    if (is_iterable($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = background_encrypt_ip($value);
        }
        return $data;
    }
    return background_encrypt_ip($data);
}

function decrypt_ip($data)
{
    if (is_iterable($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = background_decrypt_ip($value);
        }
        return $data;
    }
    return background_decrypt_ip($data);
}

// Password

function encrypt_password($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function is_valid_password($encryptedPassword, $storedPassword): bool
{
    return password_verify($encryptedPassword, $storedPassword);
}
