<?php


function load_account_toggle_functionality(Account $account, $isLoggedIn): void
{
    if (!$isLoggedIn) {
        account_page_redirect(null, false, null);
    } else {
        global $website_url;
        $functionalities = array(
            "receive_account_emails" => "Toggle Account Emails",
            "receive_marketing_emails" => "Toggle Marketing Emails",
            "receive_account_phone_messages" => "Toggle Account Phone Messages",
            "receive_marketing_phone_messages" => "Toggle Marketing Phone Messages",
            "auto_updater" => "Toggle Auto Updater",
            "two_factor_authentication" => "Toggle Two-Factor Authentication",
        );

        if (empty($functionalities)) {
            account_page_redirect($account, true, "This functionality is currently not available.");
        } else {
            $toggle = get_form_get("toggle");

            if (array_key_exists($toggle, $functionalities)) {
                $result = $account->getSettings()->toggle($toggle);
                $result = $result->getMessage();

                if (!empty($result)) {
                    $account->getNotifications()->add(AccountNotifications::FORM, "green", $result, "1 minute");
                }
                redirect_to_url("?");
            }
            echo "<div class='area'><div class='area_title'>Toggle Functionalities</div><div class='area_list' id='text'><ul>";

            foreach ($functionalities as $key => $value) {
                echo "<a href='$website_url/profile/toggleFunctionality/?toggle=$key'><li>$value</li></a>";
            }
            echo "<ul></div></div>";
        }
    }
}
