<?php
require '/var/www/.structure/library/account/api/tasks/loader.php';
load_account_page(false, function (Account $account) {
    $id = get_form_get("id");

    if (is_numeric($id) && is_private_connection()) {
        if ($account->exists()) {
            $result = $account->getDownloads()->findOrCreate(
                $id,
                1
            );

            if (!$result->isPositiveOutcome()) {
                echo json_encode($result->getMessage());
            }
        } else {
            echo json_encode("You must be logged in to download files via ID.");
        }
    } else {
        $userToken = get_form_get("userToken");

        if (!empty($userToken)) {
            if (has_memory_limit(
                get_final_directory(),
                AccountProductDownloads::TOKEN_SEARCH_LIMIT,
                AccountProductDownloads::TOKEN_SEARCH_SECONDS)
            ) {
                echo json_encode("Hit token search cooldown.");
            } else {
                $download = $account->getDownloads()->find($userToken);

                if (!$download->isPositiveOutcome()) {
                    echo json_encode("No token found (1).");
                }
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
                    $download = $account->getDownloads()->find(
                        $token,
                        false
                    );

                    if ($download->isPositiveOutcome()) {
                        $download = $download->getObject();
                        $tokenAccount = $download->account;

                        if (!$tokenAccount->exists()) {
                            echo json_encode("No account found.");
                        } else {
                            $reply = $tokenAccount->getDownloads()->create(
                                $download->product_id,
                                $download->token,
                                1,
                                true,
                                null,
                                null
                            );

                            if (!$reply->isPositiveOutcome()) {
                                echo json_encode($reply->getMessage());
                            }
                        }
                    } else {
                        echo json_encode("No token found (2).");
                    }
                }
            } else {
                echo json_encode("You specify a Token to download its correlated file.");
            }
        }
    }
});