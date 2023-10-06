<?php


require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/requirements/account_systems.php';
require_once '/var/www/.structure/library/design/accountOld/pages/basics.php';

function load_page_html_head(Account $account, $title): void
{
    global $website_url;
    $validProducts = $account->getProduct()->find();
    $metaDescription = "Vagdedes Services Store";

    if ($validProducts->isPositiveOutcome()) {
        $validProductNames = array();

        foreach ($validProducts->getObject() as $validProductObject) {
            if ($validProductObject->show_in_list !== null) {
                $validProductNames[] = strip_tags($validProductObject->name);
            }
        }
        if (!empty($validProductNames)) {
            $metaDescription .= " (" . implode(", ", $validProductNames) . ")";
        }
    }
    $randomNumber = rand(0, 2147483647);
    echo "<!DOCTYPE html>
        <html lang='en'>
        <head>" . get_google_analytics() . "
            <title>Vagdedes Services | $title</title>
            <meta name='description' content='$metaDescription'>
        	<link rel='shortcut icon' type='image/png' href='https://" . get_domain() . "/.images/icon.png'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <link rel='stylesheet' href='https://" . get_domain() . "/.css/universal.css?id=$randomNumber>'>
            <script src='https://www.google.com/recaptcha/api.js'></script>
        </head>
        <body>";
}

function load_page_intro(?Account $account, $isLoggedIn, $loadIntro, $loadNavigation): void
{
    $notification = $isLoggedIn
        ? $account->getNotifications()->get(AccountNotifications::FORM, 1, true)
        : get_form_get("message");

    if (empty($notification)) {
        $notification = "We use the necessary cookies to offer you access to our system.";

        if (!set_cookie_to_value_if_not(
            string_to_integer($notification),
            true,
            WebsiteSession::session_cookie_expiration
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

    if ($loadIntro) {
        echo "<div class='intro'>
              <div class='intro_image'>
                  <img src='https://" . get_domain() . "/.images/services.png' alt='company logo'>
              </div>
          </div>";
    } else if ($loadNavigation) {
        echo "<style>
                .footer_top#opposite {
                    border-top: none;
                }
           </style>";
    }
    if ($loadNavigation) {
        include("/var/www/.structure/library/design/accountOld/footer/footerNavigation.php");
    }
}

function load_page_footer($loadFooter): void
{
    if ($loadFooter) {
        include("/var/www/.structure/library/design/accountOld/footer/footer.php");
    }
    echo "</body></html>";
}

function load_page($loadIntro = true, $loadNavigation = true, $loadFooter = true): void
{
    $application = new Application(null);

    if (has_memory_limit(array(get_client_ip_address(), "website"), 60, "1 minute")) {
        $title = "Website Error";
        load_page_html_head($application->getAccount(0), $title);
        load_page_intro(null, false, $loadIntro, true);
        load_account_page_message($title, "Please stop refreshing the page so frequently");
    } else {
        $session = $application->getWebsiteSession();
        $sessionObject = $session->getSession();
        $account = $sessionObject->getObject();
        $isLoggedIn = $sessionObject->isPositiveOutcome() && $account->exists();

        $directory = get_final_directory();
        load_page_html_head($account, unstuck_words_from_capital_letters($directory));
        load_page_intro($account, $isLoggedIn, $loadIntro, $loadNavigation);

        if ($isLoggedIn) {
            $ban = $account->getModerations()->getReceivedAction(AccountModerations::ACCOUNT_BAN);

            if ($ban->isPositiveOutcome()) {
                if ($loadIntro || $loadFooter) {
                    load_account_page_message("Account Suspended", $ban->getMessage());
                }
                load_page_footer($loadFooter);
                return;
            }
        }
        switch ($directory) {
            case "changePassword":
                require_once '/var/www/.structure/library/design/accountOld/pages/changePassword.php';
                loadChangePassword($account, $isLoggedIn, $application);
                break;
            case "profile":
                require_once '/var/www/.structure/library/design/accountOld/pages/profile.php';
                loadProfile($account, $isLoggedIn, $application);
                break;
            case "viewProduct":
                require_once '/var/www/.structure/library/design/accountOld/pages/viewProduct.php';
                loadViewProduct($account, $isLoggedIn);
                break;
            case "changeEmail":
                require_once '/var/www/.structure/library/design/accountOld/pages/changeEmail.php';
                loadChangeEmail($account, $isLoggedIn);
                break;
            case "changeName":
                require_once '/var/www/.structure/library/design/accountOld/pages/changeName.php';
                loadChangeName($account, $isLoggedIn);
                break;
            case "viewOffer":
                global $website_url;
                $argument = get_form_get("id");
                $arguments = explode(".", $argument);
                $argumentSize = sizeof($arguments);
                $id = $arguments[$argumentSize - 1];
                $isNumericID = is_numeric($id);
                $offer = $account->getOffer()->find($isNumericID ? $id : null, false);

                if (!$offer->isPositiveOutcome()) {
                    load_account_page_message("Website Error", "This offer does not exist or is not currently available.");
                } else {
                    $offer = $offer->getObject();
                    $offerArgument = prepare_redirect_url($offer->name) . "." . $id;

                    if ($isNumericID && ($argumentSize == 1 || $argument != $offerArgument)) {
                        redirect_to_url($website_url . "/" . $directory . "/?id=" . $offerArgument, array("id"));
                    } else {
                        echo "<div class='area'>";

                        foreach ($offer->divisions as $divisions) {
                            foreach ($divisions as $division) {
                                echo $division->description;
                            }
                        }
                        echo "</div><div class='area' id='darker'>";

                        if ($isLoggedIn) {
                            echo "<div class='area_form' id='marginless'>
                                    <a href='$website_url/profile' class='button' id='green'>My Profile</a>
                                </div>";
                        } else {
                            echo "<div class='area_form' id='marginless'>
                                    <a href='$website_url/profile' class='button' id='green'>Create Your Account Today</a>
                                </div>";
                        }
                        echo "</div>";
                    }
                }
                break;
            case "addAccount":
                require_once '/var/www/.structure/library/design/accountOld/pages/addAccount.php';
                loadAddAccount($account, $isLoggedIn);
                break;
            case "downloadFile":
                $id = get_form_get("id");

                if (is_numeric($id)) {
                    if ($isLoggedIn) {
                        $result = $account->getDownloads()->sendFileDownload($id);

                        if (!$result->isPositiveOutcome()) {
                            $account->getNotifications()->add(AccountNotifications::FORM, "green", $result->getMessage(), "1 minute");
                            redirect_to_url("../viewProduct/?id=$id");
                        }
                    } else {
                        redirect_to_url("../viewProduct/?id=$id&message=You must be logged in to download this file");
                    }
                } else {
                    $token = get_form_get("token");

                    if (!empty($token)) {
                        $download = $account->getDownloads()->find($token);

                        if ($download->isPositiveOutcome()) {
                            $download = $download->getObject();
                            $tokenAccount = $download->account;

                            if (!$tokenAccount->exists()
                                || !$tokenAccount->getDownloads()->sendFileDownload(
                                    $download->product_id,
                                    $download->token
                                )->isPositiveOutcome()) {
                                exit();
                            }
                        } else {
                            exit();
                        }
                    } else {
                        exit();
                    }
                }
                break;
            case "exit":
                global $website_url;

                if ($isLoggedIn && $account->getActions()->logOut()->isPositiveOutcome()) {
                    redirect_to_url($website_url . "/profile/?message=You have been logged out");
                } else {
                    redirect_to_url($website_url . "/profile");
                }
                break;
            case "history":
                require_once '/var/www/.structure/library/design/accountOld/pages/history.php';
                loadHistory($account, $isLoggedIn);
                break;
            case "toggleFunctionality":
                require_once '/var/www/.structure/library/design/accountOld/pages/toggleFunctionality.php';
                loadToggleFunctionality($account, $isLoggedIn);
                break;
            case "instantLogin":
                if ($isLoggedIn) {
                    account_page_redirect($account, true, null);
                } else {
                    $twoFactor = $session->getTwoFactorAuthentication();
                    $twoFactor = $twoFactor->verify(get_form_get("token"));
                    account_page_redirect($twoFactor->getObject(), $twoFactor->isPositiveOutcome(), $twoFactor->getMessage());
                }
                break;
            case "help":
                require_once '/var/www/.structure/library/design/accountOld/pages/help.php';
                loadHelp($account, $isLoggedIn);
                break;
            default:
                require_once '/var/www/.structure/library/design/accountOld/pages/main.php';
                require_once '/var/www/.structure/library/design/accountOld/pages/giveaway.php';
                loadMain($account, $isLoggedIn);
                break;
        }
    }
    load_page_footer($loadFooter);
}
