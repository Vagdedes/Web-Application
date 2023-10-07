<?php

function load_account_help(Account $account, $isLoggedIn): void
{
    if (!$isLoggedIn) {
        account_page_redirect(null, false, null);
    } else {
        $code = $account->getIdentification()->get();
        echo "<div class='area'>
                <div class='area_form'>
                    <form method='post'>
                        <input type='text' id='code' name='empty' placeholder='$code' value='$code' minlength=0 maxlength=0>
                        <input type='submit' name='copy' value='Click to Copy' class='button' id='blue' onclick='copyCode()'>
                    </form>
                </div>
            </div>";
        echo "<script>
                function copyCode() {
                  var copyText = document.getElementById('code');
                  copyText.select();
                  copyText.setSelectionRange(0, 6);
                  navigator.clipboard.writeText(copyText.value);
                }
            </script>";
    }
}
