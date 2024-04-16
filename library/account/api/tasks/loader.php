<?php
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/requirements/account_systems.php';

function load_account_page(bool $loadContents = true, ?callable $callable = null, ?string $forceDirectory = null): void
{
    $directory = $forceDirectory !== null ? $forceDirectory : get_final_directory();
    $title = unstuck_words_from_capital_letters($directory);
    $metaDescription = "Science, technology & engineering";
    $randomNumber = rand(0, 2147483647);

    echo "<!DOCTYPE html>
        <html lang='en'>
        <head>" . get_google_analytics() . "
            <title>Idealistic AI | $title</title>
            <meta name='description' content='$metaDescription'>
        	<link rel='shortcut icon' type='image/png' href='" . Account::IMAGES_PATH . "idealistic/logo.png'>"
        . ($loadContents
            ? "<link rel='stylesheet' href='" . Account::WEBSITE_DESIGN_PATH . "universal.css?id=$randomNumber>'>"
            . "<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
            : "")
        . "<script src='https://www.google.com/recaptcha/api.js'></script>
        </head>
    <body>";

    if (has_memory_limit(array(get_client_ip_address(), "website"), 60, "1 minute")) {
        echo json_encode("Please stop refreshing the page so frequently.");
    } else {
        $account = new Account();
        $account = $account->getSession()->find()->getObject();

        if ($loadContents) {
            if ($account->exists()) {
                $notification = $account->getNotifications()->get(AccountNotifications::FORM, 1, true);
            } else {
                $notification = get_form_get("message");
            }
            if (!empty($notification)) {
                if (is_object($notification[0])) {
                    $notification = $notification[0]->information;
                }
                echo "<div class='message'>" . htmlspecialchars($notification, ENT_QUOTES, 'UTF-8') . "</div>";
            }
        }
        switch ($directory) {
            case "contact":
            case "exit":
            case "downloadFile":
            case "instantLogin":
            case "profile":
                $callable($account);
                break;
            default:
                if ($callable !== null) {
                    $callable();
                }
                break;
        }
    }
    echo "</body></html>";
}

function add_account_page_message(?Account $account, ?string $message): void
{
    if ($account !== null && $account->exists()) {
        $hasURLMessage = false;

        if (!empty($message)) {
            $account->getNotifications()->add(AccountNotifications::FORM, "green", $message, "1 minute");
        }
    } else {
        $hasURLMessage = !empty($message);
    }
    redirect_to_url($hasURLMessage ? "?message=" . $message : "?");
}
