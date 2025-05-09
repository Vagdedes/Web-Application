<?php

function send_discord_webhook(string                $webhookURL,
                              ?string               $avatarURL,
                              int|string|null       $color,
                              ?string               $author,
                              ?string               $authorURL,
                              ?string               $authorIconURL,
                              ?string               $title,
                              ?string               $titleURL,
                              ?string               $description,
                              ?string               $footer,
                              ?string               $footerIconURL,
                              array                 $fields,
                              int|string|float|bool $content = null): bool|string
{
    if (!is_url($webhookURL)) {
        return "Local: Failed webhook-url criteria";
    } else if (!in_array(get_domain_from_url($webhookURL, true), DiscordWebhookVariables::ACCEPTED_DOMAINS)) {
        return "Local: Failed to find domain";
    }
    $hasColor = $color !== null;

    if ($hasColor && (strlen($color) < 3 || strlen($color) > 6)) {
        return "Local: Failed color criteria";
    }
    $hasAuthorURL = !empty($authorURL);

    if ($hasAuthorURL && !is_url($authorURL)) {
        return "Local: Failed author-url criteria";
    }
    $hasAuthorIconURL = !empty($authorIconURL);

    if ($hasAuthorIconURL && !is_url($authorIconURL)) {
        return "Local: Failed author-icon-url criteria";
    }
    $hasAvatarURL = !empty($avatarURL);

    if ($hasAvatarURL && !is_url($avatarURL)) {
        return "Local: Failed avatar-url criteria";
    }
    $hasFooterURL = !empty($footerIconURL);

    if ($hasFooterURL && !is_url($footerIconURL)) {
        return "Local: Failed footer-url criteria";
    }
    $hasTitleURL = !empty($titleURL);

    if ($hasTitleURL && !is_url($titleURL)) {
        return "Local: Failed title-url criteria";
    }
    if (!empty($title) && strlen($title) > 256) {
        return "Local: Failed title criteria";
    }
    $hasFooter = !empty($footer);

    if ($hasFooter && strlen($footer) > 2048) {
        return "Local: Failed footer criteria";
    }
    $hasDescription = !empty($description);

    if ($hasDescription && strlen($description) > 2048) {
        return "Local: Failed description criteria";
    }
    $hasTitle = !empty($title);

    if ($hasTitle && strlen($title) > 256) {
        return "Local: Failed title criteria";
    }
    $hasAuthor = !empty($author);

    if ($hasAuthor && strlen($author) > 256) {
        return "Local: Failed author criteria";
    }
    $sum = ($hasTitle ? strlen($title) : 0)
        + ($hasDescription ? strlen($description) : 0)
        + ($hasFooter ? strlen($footer) : 0)
        + ($hasAuthor ? strlen($author) : 0);

    if (!empty($fields)) {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                return "Local: Failed fields criteria (not array)";
            }
            if (count($field) !== 3) {
                return "Local: Failed fields criteria (count)";
            }
            $len = strlen($field["name"]);

            if ($len > 256) {
                return "Local: Failed fields criteria (name)";
            }
            $sum += $len;

            if ($sum > 6000) {
                return "Local: Failed fields criteria (sum)";
            }
            $len = strlen($field["value"]);

            if ($len > 1024) {
                return "Local: Failed fields criteria (value)";
            }
            $sum += $len;

            if ($sum > 6000) {
                return "Local: Failed fields criteria (sum)";
            }
            if (!is_bool($field["inline"])) {
                return "Local: Failed fields criteria (inline)";
            }
        }
    } else {
        $fields = array();

        if ($sum > 6000) {
            return "Local: Failed fields criteria (sum)";
        }
    }
    $array = array(
        "content" => $content !== null ? $content : "",
        "avatar_url" => ($hasAvatarURL ? $avatarURL : ""),
        "tts" => false,
        "file" => "",

        // Embeds Array
        "embeds" => array(
            array(
                "title" => ($hasTitle ? $title : ""),
                "type" => "rich",
                "description" => ($hasDescription ? $description : ""),
                "url" => ($hasTitleURL ? $titleURL : ""),
                "timestamp" => date("c", strtotime("now")),
                "color" => ($hasColor ? hexdec($color) : "000000"),
                "footer" => array(
                    "text" => ($hasFooter ? $footer : ""),
                    "icon_url" => ($hasFooterURL ? $footerIconURL : "")
                ),
                "author" => array(
                    "name" => ($hasAuthor ? $author : ""),
                    "url" => ($hasAuthor && $hasAuthorURL ? $authorURL : ""),
                    "icon_url" => ($hasAuthor && $hasAuthorIconURL ? $authorIconURL : ""),
                ),
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    return strlen($response) === 0 ? true : $response;
}