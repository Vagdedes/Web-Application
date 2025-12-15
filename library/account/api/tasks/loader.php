<?php
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/requirements/account_systems.php';

function load_account_page(?callable $callable = null, ?string $forceDirectory = null): void
{
    $directory = $forceDirectory !== null ? $forceDirectory : get_final_directory();
    $title = unstuck_words_from_capital_letters($directory);

    echo "<!DOCTYPE html>
        <html lang='en'>
        <head>" . get_google_analytics() . "
            <title>Idealistic AI | $title</title>"
        . "</head>
    <body>";

    if (has_memory_limit(array(get_client_ip_address(), "website"), 60, "1 minute")) {
        echo json_encode("Please stop refreshing the page so frequently.");
    } else {
        $account = new Account();
        $account = $account->getSession()->find()->getObject();
        $callable($account);
    }
    echo "</body></html>";
}
