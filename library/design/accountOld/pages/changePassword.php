<?php

function loadChangePassword(Account $account, $isLoggedIn, Application $application)
{
    $token = get_form_get("token");

    if (!empty($token)) {
        if (isset($_POST["change"])) {
            $password = get_form_post("password");

            if ($password == get_form_post("repeat_password")) {
                $result = $account->getPassword()->completeChange($token, $password);
                $result = $result->getMessage();

                if (!empty($result)) {
                    $account->getNotifications()->add("green", "form", $result, "1 minute");
                }
                redirect_to_url("?");
            } else {
                redirect_to_url("?message=Passwords do not match each other&token=$token");
            }
        }

        echo "<div class='area'>
                <div class='area_form'>
                    <form method='post'>
                        <input type='password' name='password' placeholder='New Password' minlength=8 maxlength=64>
                        <input type='password' name='repeat_password' placeholder='Repeat New Password' minlength=8 maxlength=64>
                        <input type='submit' name='change' value='Complete Change Password' class='button' id='blue'>
                    </form>
                </div>
            </div>";
    } else if ($isLoggedIn) {
        $email = $account->getDetail("email_address");

        if (isset($_POST["change"])) {
            $result = $account->getPassword()->requestChange();
            $result = $result->getMessage();

            if (!empty($result)) {
                $account->getNotifications()->add("green", "form", $result, "1 minute");
            }
            redirect_to_url("?");
        }

        echo "<div class='area'>
                <div class='area_form'>
                    <form method='post'>
                        <input type='email' name='email' placeholder='$email' minlength=0 maxlength=0>
                        <input type='submit' name='change' value='Request Change Password' class='button' id='blue'>
                    </form>
                </div>
            </div>";
    } else {
        if (isset($_POST["change"])) {
            if (!is_google_captcha_valid()) {
                redirect_to_url("?message=Please complete the bot verification");
            } else {
                $account = $application->getAccount(null, get_form_post("email"));

                if ($account->exists()) {
                    $result = $account->getPassword()->requestChange();
                    $result = $result->getMessage();

                    if (!empty($result)) {
                        $account->getNotifications()->add("green", "form", $result, "1 minute");
                    }
                    redirect_to_url("?");
                } else {
                    redirect_to_url("?message=Account with this email address does not exist");
                }
            }
        }

        echo "<div class='area'>
                <div class='area_form'>
                    <form method='post'>
                        <input type='email' name='email' placeholder='Email Address' minlength=5 maxlength=384>
                        <input type='submit' name='change' value='Request Change Password' class='button' id='blue'>

                         <div class=recaptcha>
    		                <div class=g-recaptcha data-sitekey=6Lf_zyQUAAAAAAxfpHY5Io2l23ay3lSWgRzi_l6B></div>
    		            </div>
                    </form>
                </div>
            </div>";
    }
}
