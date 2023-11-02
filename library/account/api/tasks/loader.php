<?php
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/requirements/account_systems.php';
$website_account_url = "https://" . get_domain() . "/account";

function load_page_intro(?Account $account, bool $isLoggedIn, bool $loadNavigation): void
{
    $notification = $isLoggedIn
        ? $account->getNotifications()->get(AccountNotifications::FORM, 1, true)
        : get_form_get("message");

    if (empty($notification)) {
        $notification = "We use the necessary cookies to offer you access to our system.";

        if (!set_cookie_to_value_if_not(
            string_to_integer($notification),
            true,
            AccountSession::session_cookie_expiration
        )) {
            $notification = null;
        }
    }
    if (!empty($notification)) {
        if (is_object($notification[0])) {
            $notification = $notification[0]->information;
        }
        echo "<div class='message'>" . htmlspecialchars($notification, ENT_QUOTES, 'UTF-8') . "</div>";
    }

    if ($loadNavigation) {
        include("/var/www/.structure/library/design/account/footer/footerNavigation.php");
    }
}

function load_page(bool $loadContents = true, ?callable $callable = null): void
{
    $directory = get_final_directory();
    $title = unstuck_words_from_capital_letters($directory);
    $metaDescription = "";
    $randomNumber = rand(0, 2147483647);

    echo "<!DOCTYPE html>
        <html lang='en'>
        <head>" . get_google_analytics() . "
            <title>Idealistic AI | $title</title>
            <meta name='description' content='$metaDescription'>
        	<link rel='shortcut icon' type='image/png' href='" . Application::IMAGES_PATH . "icon.png'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link rel='stylesheet' href='" . Application::WEBSITE_DESIGN_PATH . "universal.css?id=$randomNumber>'>
            <script src='https://www.google.com/recaptcha/api.js'></script>
        </head>
    <body>";

    if (has_memory_limit(array(get_client_ip_address(), "website"), 60, "1 minute")) {
        load_page_intro(null, false, $loadContents);
        load_account_page_message("Website Error", "Please stop refreshing the page so frequently");
    } else {
        $application = new Application(null);
        $session = $application->getAccountSession();
        $sessionObject = $session->getSession();
        $account = $sessionObject->getObject();
        $isLoggedIn = $sessionObject->isPositiveOutcome() && $account->exists();
        load_page_intro($account, $isLoggedIn, $loadContents);

        switch ($directory) {
            case "changePassword":
                $callable($account, $isLoggedIn, $application);
                break;
            case "profile":
                $callable($isLoggedIn, $application);
                break;
            case "contact":
            case "exit":
            case "changeEmail":
            case "downloadFile":
                $callable($account, $isLoggedIn);
                break;
            case "instantLogin":
                $callable($account, $isLoggedIn, $session);
                break;
            default:
                if ($callable !== null) {
                    $callable();
                }
                break;
        }
    }
    if ($loadContents) {
        include("/var/www/.structure/library/design/account/footer/footer.php");
    }
    echo "</body></html>";
}

function load_account_page_message($title, $reason): void
{
    echo "<div class='area'>
            <div class='area_logo'>
                <div class='question_mark'></div>
            </div>
            <div class='area_title'>
                $title
            </div>
            <div class='area_text'>
                $reason
            </div>
        </div>";
}

function account_page_redirect(?Account $account, bool $isLoggedIn, ?string $message): void
{
    global $website_account_url;
    $redirectURL = get_user_url();

    if ($isLoggedIn) {
        $hasURLMessage = false;

        if (!empty($message)) {
            $account->getNotifications()->add(AccountNotifications::FORM, "green", $message, "1 minute");
        }
    } else {
        $hasURLMessage = !empty($message);
    }
    redirect_to_url($website_account_url . "/profile/"
        . ($hasURLMessage ? "?message=" . $message : "")
        . (starts_with($redirectURL, $website_account_url) ? ($hasURLMessage ? "&" : "?") . "redirectURL=" . $redirectURL : ""));
}
