<?php

function send_discord_webhook(string     $webhookURL,
                              ?string    $avatarURL,
                              int|string $color,
                              ?string    $authorName, ?string $authorURL,
                              ?string    $authorIconURL, string $titleName,
                              ?string    $titleURL,
                              ?string    $description, ?string $footerName,
                              ?string    $footerURL, array $fields, int|string|float|bool $content = null): bool|string
{
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
    $hasAuthorURL = $authorURL !== null;

    if ($hasAuthorURL && !is_url($authorURL)) {
        return "Local: Failed author-url criteria";
    }
    $hasAuthorIconURL = $authorIconURL !== null;

    if ($hasAuthorIconURL && !is_url($authorIconURL)) {
        return "Local: Failed author-icon-url criteria";
    }
    $hasAvatarURL = $avatarURL !== null;

    if ($hasAvatarURL && !is_url($avatarURL)) {
        return "Local: Failed avatar-url criteria";
    }
    $hasFooterURL = $footerURL !== null;

    if ($hasFooterURL && !is_url($footerURL)) {
        return "Local: Failed footer-url criteria";
    }
    $hasTitleURL = $titleURL !== null;

    if ($hasTitleURL && !is_url($titleURL)) {
        return "Local: Failed title-url criteria";
    }
    if (!empty($titleName) && strlen($titleName) > 64) {
        return "Local: Failed title-name criteria";
    }
    $hasFooterName = $footerName !== null;

    if ($hasFooterName && strlen($footerName) > 64) {
        return "Local: Failed footer-name criteria";
    }
    $hasAuthorName = $authorName !== null;
    $hasDescription = $description !== null;
    $array = array(
        "content" => $content !== null ? $content : "",
        "avatar_url" => ($hasAvatarURL ? $avatarURL : ""),
        "tts" => false,
        "file" => "",

        // Embeds Array
        "embeds" => array(
            array(
                "title" => $titleName,
                "type" => "rich",
                "description" => ($hasDescription ? $description : ""),
                "url" => ($hasTitleURL ? $titleURL : ""),
                "timestamp" => date("c", strtotime("now")),
                "color" => hexdec($color),

                // Footer
                "footer" => array(
                    "text" => ($hasFooterName ? $footerName : ""),
                    "icon_url" => ($hasFooterURL ? $footerURL : "")
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
                    "name" => ($hasAuthorName ? $authorName : ""),
                    "url" => ($hasAuthorName && $hasAuthorURL ? $authorURL : ""),
                    "icon_url" => ($hasAuthorName && $hasAuthorIconURL ? $authorIconURL : ""),
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