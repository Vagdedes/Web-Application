<?php
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/requirements/account_systems.php';

if (has_memory_limit(array(get_client_ip_address(), "verifyDownload"), 30, "1 minute")) {
    return;
}
$token = get_form_get("token");
$cacheKey = array(
    $token,
    "verifyDownload"
);
$cache = get_key_value_pair($cacheKey);

if (is_string($cache) || is_numeric($cache) && $cache > 0) {
    echo $cache;
} else {
    $application = new Application(null);
    $download = $application->getAccount(0);
    $download = $download->getDownloads()->find(properly_sql_encode($token));
    $result = "false";

    if ($download->isPositiveOutcome()) {
        $download = $download->getObject();
        $account = $download->account;

        if ($account->exists()
            && $account->getPurchases()->owns($download->product_id)->isPositiveOutcome()) {
            $acceptedPlatforms = get_accepted_platforms(array("id", "accepted_account_id"));

            if (!empty($acceptedPlatforms)) {
                foreach ($acceptedPlatforms as $row) {
                    $acceptedAccount = $account->getAccounts()->hasAdded($row->accepted_account_id, null, 1);

                    if ($acceptedAccount->isPositiveOutcome()) {
                        $result = $acceptedAccount->getObject()[0];
                        break;
                    }
                }
            }
        }
    }
    echo $result;
}
