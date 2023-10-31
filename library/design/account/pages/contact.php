<?php

function load_account_contact(Account $account, $isLoggedIn): void
{
    if (!$isLoggedIn) {
        account_page_redirect(null, false, null);
    } else {
        if (isset($_POST["contact"])) {
            $subject = get_form_post("subject");
            $info = get_form_post("info");
            $subjectString = strlen($subject);
            $infoString = strlen($info);

            if ($subjectString >= 2 && $subjectString <= 64
                && $infoString >= 24 && $infoString <= 512) {
                $cacheKey = array(get_client_ip_address(), "contact-form");

                if (has_memory_cooldown($cacheKey, null, false)) {
                    account_page_redirect($account, true, "Please wait a few minutes before contacting us again.");
                } else {
                    $platformsString = null;
                    $accounts = $account->getAccounts()->getAdded();

                    if (!empty($accounts)) {
                        $platformsString = "Accounts:\r\n";

                        foreach ($accounts as $row) {
                            $platformsString .= $row->accepted_account->name . ": " . $row->credential . "\r\n";
                        }
                        $platformsString .= "\r\n";
                    }
                    $id = rand(0, 2147483647);
                    $email = $account->getDetail("email_address");
                    $subject = strip_tags($subject);
                    $title = get_domain() . " - $subject [ID: $id]";
                    $content = "ID: $id" . "\r\n"
                        . "Subject: $subject" . "\r\n"
                        . "Email: $email" . "\r\n"
                        . "\r\n"
                        . ($platformsString !== null ? $platformsString : "")
                        . strip_tags($info);

                    if (services_self_email($email, $title, $content)) {
                        has_memory_cooldown($cacheKey, "5 minutes");
                        account_page_redirect($account, true, "Thanks for taking the time to contact us.");
                    } else {
                        global $email_default_email_name;
                        account_page_redirect($account, true, "An error occurred, please contact us at: " . $email_default_email_name);
                    }
                }
            }
        }
        echo "<div class='area' id='darker'>
                <div class='area_logo'>
                    <div class='paper'>
                        <ul>
                            <li class='paper_top'></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                            <li></li>
                        </ul>
                    </div>
                </div>
                <div class='area_title'>
                    Contact Form
                </div>
                <div class='area_text'>
                    Contact us by using the form below.
                </div>
                <div class='area_form'>
                    <form method='post'>
                        <input type='text' name='subject' placeholder='Subject' minlength=2 maxlength=64>
                        <textarea name='info' placeholder='Information regarding contact...' minlength=24 maxlength=512
                                  style='height: 150px; min-height: 150px;'></textarea>
                        <input type='submit' name='contact' value='Contact Us' class='button' id='blue'>
                    </form>
                </div>
            </div>";
    }
}
