<?php


function load_account_profile($isLoggedIn, Application $application): void
{
    global $website_account_url;

    if ($isLoggedIn) {
        echo "<div class='area'>
            <div class='area_title'>Welcome to Your Account</div>
            <div class='area_text'>To manage your account, please join our Discord via the button below.</div>
            <p>
            <div class='area_form' id='marginless'>
                <a href='https://" . get_domain() . "/discord' class='button' id='blue'>Join Discord</a>
                <p>
                <a href='$website_account_url/exit' class='button' id='red'>Log Out</a>
            </div></div>
            </div>";
    } else {
        if (isset($_POST["log_in"])) {
            if (!is_google_captcha_valid()) {
                account_page_redirect(null, false, "Please complete the bot verification.");
            } else {
                $email = get_form_post("email");

                if (!is_email($email)) {
                    redirect_to_url("?message=Please enter a valid email address");
                } else {
                    $account = $application->getAccount(null, $email);

                    if ($account->exists()) {
                        $result = $account->getActions()->logIn(get_form_post("password"));

                        if ($result->isPositiveOutcome()) {
                            $redirectURL = get_form_get("redirectURL");

                            if (starts_with($redirectURL, $website_account_url)) {
                                redirect_to_url($redirectURL);
                            } else {
                                account_page_redirect($account, true, $result->getMessage());
                            }
                        } else {
                            account_page_redirect(null, false, $result->getMessage());
                        }
                    } else {
                        account_page_redirect(null, false, "Account with this email does not exist.");
                    }
                }
            }
        }
        echo "<div class='area' id='darker'>
            <div class='area_form'>
                <form method='post'>
                    <input type='email' name='email' placeholder='Email Address' minlength=5 maxlength=384>
                    <input type='password' name='password' placeholder='Password' minlength=8 maxlength=384>
                    <input type='submit' name='log_in' value='Log In' class='button' id='blue'>

                     <div class=recaptcha>
		                <div class=g-recaptcha data-sitekey=6Lf_zyQUAAAAAAxfpHY5Io2l23ay3lSWgRzi_l6B></div>
		            </div>
                </form>
            </div>
        </div>";

        echo "<div class='area'>
                <div class='area_form' id='marginless'>
                    <a href='$website_account_url/profile/changePassword' class='button' id='red'>Forgot My Password</a>
                </div>
            </div>";
    }
}
