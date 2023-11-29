<?php

function send_discord_webhook(string  $webhookURL, int|string $color,
                              ?string $gameName, ?string $serverName,
                              ?string $avatarURL, ?string $iconURL, ?string $titleURL,
                              string  $titleName, ?string $footerName,
                              array   $fields,
                              ?string $username = null, int|string|float|bool $content = null): bool|string
{
    $hasServerName = $serverName !== null;
    $hasGameName = $gameName !== null;
    $hasAvatarURL = $avatarURL !== null;
    $hasIconURL = $iconURL !== null;
    $hasTitleURL = $titleURL !== null;
    $hasFooterName = $footerName !== null;

    if (!is_url($webhookURL)) {
        return "Local: Failed webhook-url criteria";
    } else {
        global $discord_webhooks_accepted_domains;

        if (!in_array(get_domain_from_url($webhookURL, true), $discord_webhooks_accepted_domains)) {
            return "Local: Failed to find domain";
        }
    }
    if (strlen($color) < 3 || strlen($color) > 6) {
        return "Local: Failed color criteria";
    }
    if ($hasServerName && (empty($serverName) || strlen($serverName) > 32)) {
        return "Local: Failed server-name criteria";
    }
    if ($hasAvatarURL && !is_url($avatarURL)) {
        return "Local: Failed avatar-url criteria";
    }
    if ($hasIconURL && !is_url($iconURL)) {
        return "Local: Failed icon-url criteria";
    }
    if ($hasTitleURL && !is_url($titleURL)) {
        return "Local: Failed title-url criteria";
    }
    if (!empty($titleName) && strlen($titleName) > 64) {
        return "Local: Failed title-name criteria";
    }
    if ($hasFooterName && strlen($footerName) > 64) {
        return "Local: Failed footer-name criteria";
    }
    if ($username !== null) {
        //$fields["username"] = $username;
    }
    $array = array(
        "content" => $content !== null ? $content : "",
        //"username" => $username !== null ? $username : "",
        "avatar_url" => ($hasAvatarURL ? $avatarURL : ""),
        "tts" => false,
        "file" => "",

        // Embeds Array
        "embeds" => array(
            array(
                "title" => $titleName,
                "type" => "rich",
                "description" => "",
                "url" => ($hasTitleURL ? $titleURL : ""),
                "timestamp" => date("c", strtotime("now")),
                "color" => hexdec($color),

                // Footer
                "footer" => array(
                    "text" => ($hasFooterName ? $footerName : ""),
                    "icon_url" => ($hasIconURL ? $iconURL : "")
                ),

                // Image to send
                //"image" => [
                //    "url" => ""
                //],

                // Thumbnail
                //"thumbnail" => [
                //    "url" => ""
                //],

                // Author
                "author" => array(
                    "name" => ($hasGameName ? $gameName . ($hasServerName && $serverName != "NULL" ? " (" . $serverName . ")" : "") : ""),
                    "url" => ""
                ),

                // Additional Fields array
                "fields" => $fields
            )
        )
    );

    $ch = curl_init($webhookURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return strlen($response) === 0 ? true : $response;
}