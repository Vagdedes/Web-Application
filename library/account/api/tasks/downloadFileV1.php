<?php
require '/var/www/.structure/library/account/api/tasks/loader.php';
load_page(false, function (Account $account) {
    $id = get_form_get("id");

    if (is_numeric($id)) {
        if ($account->exists()) {
            $result = $account->getDownloads()->getOrCreateValidToken($id, 1, true);

            if (!$result->isPositiveOutcome()) {
                echo json_encode($result->getMessage());
            }
        } else {
            echo json_encode("You must be logged in to download files via ID.");
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
            echo json_encode("You specify a Token to download its correlated file.");
        }
    }
}
);