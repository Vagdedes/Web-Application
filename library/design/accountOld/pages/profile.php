<?php


function load_account_profile(Account $account, $isLoggedIn, Application $application): void
{
    global $website_url;

    if ($isLoggedIn) {
        global $add_account_url;
        $add_account_url = $website_url . "/profile/addAccount";
        $objectives = $account->getObjectives()->get();

        if (!empty($objectives)) {
            $style = count($objectives) <= 2 ? " style='height: auto;'" : "";
            echo "<div class='area'>";
            echo "<div class='area_list' style='margin-top: 0px;'><ul>";

            foreach ($objectives as $objective) {
                $title = $objective->url !== null ?
                    "<a href='{$objective->url}'>{$objective->title}</a>" :
                    $objective->title;
                echo "<li$style><div class='area_list_title'>$title</div>
                          <div class='area_list_contents'>{$objective->description}</div>
                      </li>";
            }
            echo "</ul></div></div>";
        }

        echo "<div class='area' id='darker'><div class='area_title'>Connected Accounts</div>";
        $platformShowcase = "";
        $alternateAccounts = $account->getAccounts()->getAdded(null, 10);

        if (!empty($alternateAccounts)) {
            $platformShowcase .= "<div class='product_list'><ul>";

            foreach ($alternateAccounts as $alternateAccount) {
                $alternateAccount = $alternateAccount->accepted_account;
                $image = $alternateAccount->image;

                if ($image !== null) {
                    $name = $alternateAccount->name;
                    $url = $alternateAccount->url;

                    $platformShowcase .= "<li><a href='$url'>
                            <div class='product_list_contents' style='background-image: url($image);'>
                                <div class='product_list_title'>$name</div>
                            </div>
                        </a>
                    </li>";
                }
            }
            $platformShowcase .= "</ul></div>";
            echo "<div class='area_text'>Here you will find many of your recently added accounts.</div>";
        } else {
            $platformShowcase .= "</private_verification_key><div class='area_text'>You haven't added any accounts yet.</div>";
        }
        $platformShowcase .= "<p><div class='area_form' id='marginless'>
                                <a href='$add_account_url' class='button' id='blue'>Add Account</a>
                            </div></div>";
        echo $platformShowcase;

        // Separator

        echo "<div class='area'>
            <div class='area_title'>Account Actions</div>
            <div class='area_list' id='text'>
                <ul>
                    <a href='$website_url/profile/changeName'><li>Change Username</li></a>
                    <a href='$website_url/profile/changeEmail'><li>Change Email</li></a>
                    <a href='$website_url/profile/changePassword'><li>Change Password</li></a>
                    <a href='$website_url/profile/toggleFunctionality'><li>Toggle Functionalities</li></a>
                    <a href='$website_url/profile/history'><li>View History</li></a>
                    <a href='$website_url/profile/help'><li>View Support Code</li></a>
                <ul>
            </div><p>
            <div class='area_form' id='marginless'>
                <a href='$website_url/exit' class='button' id='red'>Log Out</a>
            </div></div>";
    } else {
        if (isset($_POST["register"])) {
            if (!is_google_captcha_valid()) {
                account_page_redirect(null, false, "Please complete the bot verification.");
            } else {
                $accountRegistry = $application->getAccountRegistry(
                    get_form_post("email"),
                    get_form_post("password"),
                    get_form_post("name"),
                    null,
                    null,
                    null,
                    AccountRegistry::DEFAULT_WEBHOOK
                )->getOutcome();
                account_page_redirect($accountRegistry->getObject(), $accountRegistry->isPositiveOutcome(), $accountRegistry->getMessage());
            }
        } else if (isset($_POST["log_in"])) {
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

                            if (starts_with($redirectURL, $website_url)) {
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
        $legal = "https://docs.google.com/document/d/e/2PACX-1vQv3w35tedzwTKAeouxTs9w5Datl8SPosZE4zwNuMb0j2MWHc4wxaY6SAtjhuMY-SXD4jYfNjRrJLK-/pub";
        echo "<div class='areas' id='darker'>";
        echo "<div class='area50' id='darker'>
            <div class='area_form' id='full'>
                <form method='post'>
                    <input type='email' name='email' placeholder='Email Address' minlength=5 maxlength=384>
                    <input type='password' name='password' placeholder='Password' minlength=8 maxlength=64>
                    <input type='text' name='name' placeholder='Username' minlength=2 maxlength=20>
                    <input type='submit' name='register' value='Register Account' class='button' id='green'>

                     <div class=recaptcha>
		                <div class=g-recaptcha data-sitekey=6Lf_zyQUAAAAAAxfpHY5Io2l23ay3lSWgRzi_l6B></div>
		            </div>
                </form>
            </div>
            <p>
            <div class='area_text'>By registering an account, you acknowledge and accept this platforms/service's <a href='$legal'>legal information</a>.</div>
        </div>";

        echo "<div class='area50' id='darker'>
            <div class='area_form' id='full'>
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
        echo "</div>";

        echo "<div class='area'>
                <div class='area_form' id='marginless'>
                    <a href='$website_url/profile/changePassword' class='button' id='red'>Forgot My Password</a>
                </div>
            </div>";
    }
}
