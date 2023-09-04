<?php

function loadChangeName(Account $account, $isLoggedIn)
{
    if (!$isLoggedIn) {
        redirect_to_account_page(null, false, null);
    } else {
        if (isset($_POST["change"])) {
            $result = $account->getActions()->changeName(get_form_post("name"));
            $result = $result->getMessage();

            if (!empty($result)) {
                $account->getNotifications()->add("green", "form", $result, "1 minute");
            }
            redirect_to_url("?");
        }
        echo "<div class='area'>
            <div class='area_form'>
                <form method='post'>
                    <input type='text' name='name' placeholder='Username' minlength=3 maxlength=16>
                    <input type='submit' name='change' value='Change Name' class='button' id='blue'>
                </form>
            </div>
        </div>";
    }
}
