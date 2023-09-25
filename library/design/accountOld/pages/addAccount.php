<?php


function loadAddAccount(Account $account, $isLoggedIn)
{
    if (!$isLoggedIn) {
        account_page_redirect(null, false, "You must be logged in to add an account.");
    } else {
        global $accepted_accounts_table;
        $acceptedAccounts = get_sql_query(
            $accepted_accounts_table,
            array("name"),
            array(
                array("manual", "IS NOT", null),
                array("deletion_date", null),
                array("application_id", $account->getDetail("application_id"))
            )
        );

        if (empty($acceptedAccounts)) {
            account_page_redirect($account, true, "This functionality is currently not available.");
        } else {
            if (isset($_POST["add"])) {
                $result = $account->getAccounts()->add(
                    get_form_post("type"),
                    get_form_post("information")
                );
                $result = $result->getMessage();

                if (!empty($result)) {
                    $account->getNotifications()->add(AccountNotifications::FORM, "green", $result, "1 minute");
                }
                redirect_to_url("?");
            }

            echo "<div class='area'>
                <div class='area_form'>
                    <form method='post'>";

            echo "<input list='type' name='type' placeholder='type'>";
            echo "<datalist id='type'>";

            foreach ($acceptedAccounts as $acceptedAccount) {
                echo "<option value='{$acceptedAccount->name}'>";
            }
            echo "</datalist>";

            echo "<input type='text' name='information' placeholder='Account Information' minlength=1 maxlength=384>
                        <input type='submit' name='add' value='Add Account' class='button' id='blue'>
                    </form>
                </div>
            </div>";
        }
    }
}
