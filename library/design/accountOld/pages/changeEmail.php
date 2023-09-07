<?php


function loadChangeEmail(Account $account, $isLoggedIn)
{
    if (!$isLoggedIn) {
        $token = get_form_get("token");

        if (!empty($token)) {
            echo "<div class='area'>
                    <div class='area_form'>
                        <form method='post'>
                            <input type='email' name='email' placeholder='New Email Address' minlength=0 maxlength=0>
                            <input type='submit' name='change' value='You Must Be Logged In' class='button' id='blue'>
                        </form>
                    </div>
                </div>";
        } else {
            redirect_to_account_page(null, false, null);
        }
    } else {
        $token = get_form_get("token");

        if (!empty($token)) {
            $result = $account->getEmail()->completeVerification($token);
            redirect_to_account_page($account, true, $result->getMessage());
        } else {
            if (isset($_POST["change"])) {
                $result = $account->getEmail()->requestVerification(get_form_post("email"));
                $result = $result->getMessage();

                if (!empty($result)) {
                    $account->getNotifications()->add(AccountNotifications::FORM, "green", $result, "1 minute");
                }
                redirect_to_url("?");
            }

            echo "<div class='area'>
                    <div class='area_form'>
                        <form method='post'>
                            <input type='email' name='email' placeholder='New Email Address' minlength=5 maxlength=384>
                            <input type='submit' name='change' value='Request Change Email' class='button' id='blue'>
                        </form>
                    </div>
                </div>";
        }
    }
}
