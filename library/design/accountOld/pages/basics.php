<?php

function prepare_redirect_url($string): string
{
    return str_replace(" ", "-", str_replace(".", "-", strip_tags($string)));
}

function account_product_prompt(?Account $account, $isLoggedIn, $productObject): string
{
    $view = "Click To View";
    $free = "<b>FREE</b>";

    if ($isLoggedIn) {
        if ($account->getGiveaway()->hasWon($productObject->id)) {
            return "Won From Giveaway";
        } else if ($productObject->is_free) {
            return !empty($productObject->downloads)
                ? $free
                : $view;
        } else if ($account->getPurchases()->owns($productObject->id)->isPositiveOutcome()) {
            return !empty($productObject->downloads)
                ? "Downloadable"
                : "Purchased";
        } else {
            return $view;
        }
    } else {
        return $productObject->requires_account !== null
            ? "Log In To View"
            : (!$productObject->is_free ? $view
                : (!empty($productObject->downloads) ? $free : $view));
    }
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

function account_page_redirect(?Account $account, bool $isLoggedIn, string $message): void
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
