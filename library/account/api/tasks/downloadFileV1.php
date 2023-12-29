<?php
require '/var/www/.structure/library/account/api/tasks/loader.php';
load_page(
    false,
    function (Account $account, bool $isLoggedIn) {
    $id = get_form_get("id");

    if (is_numeric($id)) {
        if ($isLoggedIn) {
            $result = $account->getDownloads()->getOrCreateValidToken($id, 1, true);

            if (!$result->isPositiveOutcome()) {
                account_page_redirect($account, true, $result->getMessage());
            }
        } else {
            global $website_account_url;
            redirect_to_url($website_account_url . "/profile/"
                . "?redirectURL=" . get_user_url()
                . "&message=You must be logged in to download this file.");
        }
    } else {
        $token = get_form_get("token");

        if (!empty($token)) {
            if (has_memory_limit(
                get_final_directory(),
                AccountProductDownloads::TOKEN_SEARCH_LIMIT,
                AccountProductDownloads::TOKEN_SEARCH_SECONDS)
            ) {
                echo json_encode("Hit token search cooldown.");
            } else {
                $download = $account->getDownloads()->find($token);

                if ($download->isPositiveOutcome()) {
                    $download = $download->getObject();
                    $tokenAccount = $download->account;

                    if (!$tokenAccount->exists()) {
                        echo json_encode("No account found.");
                    } else {
                        $reply = $tokenAccount->getDownloads()->makeFileDownload(
                            $download->product_id,
                            $download->token,
                            1
                        );

                        if (!$reply->isPositiveOutcome()) {
                            echo json_encode($reply->getMessage());
                        }
                    }
                } else {
                    echo json_encode("No token found.");
                }
            }
        } else {
            account_page_redirect(null, false, "You must be logged in to access downloads.");
        }
    }
},
false
);