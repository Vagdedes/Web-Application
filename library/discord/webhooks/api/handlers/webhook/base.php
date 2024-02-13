<?php

function send_discord_webhook(string                $webhookURL,
                              ?string               $avatarURL,
                              int|string            $color,
                              ?string               $authorName, ?string $authorURL,
                              string                $titleName, ?string $titleURL,
                              ?string               $footerName, ?string $footerURL,
                              array                 $fields,
                              int|string|float|bool $content = null): bool|string
{
    $hasAuthorURL = $authorURL !== null;
    $hasAvatarURL = $avatarURL !== null;
    $hasFooterURL = $footerURL !== null;
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
    if ($hasAuthorURL && !is_url($authorURL)) {
        return "Local: Failed author-url criteria";
    }
    if ($hasAvatarURL && !is_url($avatarURL)) {
        return "Local: Failed avatar-url criteria";
    }
    if ($hasFooterURL && !is_url($footerURL)) {
        return "Local: Failed footer-url criteria";
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
    $hasAuthorName = $authorName !== null;
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
                "description" => "",
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