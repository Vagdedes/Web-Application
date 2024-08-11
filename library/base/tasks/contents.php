<?php
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/base/requirements/account_systems.php';
require '/var/www/.structure/library/finance/init.php';
$path = get_form_get("path");

if (!empty($path)) {
    $account = new Account();
    $session = $account->getSession()->find();

    if ($session->isPositiveOutcome()) {
        if ($session->getObject()->getPermissions()->hasPermission(
            "view.path." . str_replace("/", ".", $path)
        )) {
            set_session_account_id($session->getObject()->getDetail("id"));
            unset($_GET["path"]);
            $domain = get_form_get("domain");

            if (empty($domain)) {
                $domain = "https://" . get_domain();
            } else {
                if (is_numeric(str_replace(".", "", $domain))) { // Ip Address
                    $domain = "http://" . $domain;
                } else {
                    $domain = "https://" . $domain;
                }
                unset($_GET["domain"]);
            }
            $url = $domain . "/" . $path . "/?" . http_build_query($_GET);
            $contents = private_file_get_contents($url);

            if (json_decode($contents)) {
                if (isset($_GET["download"])) {
                    copy_and_send_file_download($contents, Account::DOWNLOADS_PATH);
                } else {
                    header('Content-type: Application/JSON');
                }
            }
            echo $contents;
        }
    } else {
        include '/var/www/idealistic/account/profile/index.php';
    }
} else if (is_private_connection(true)) {
    header('Content-type: Application/JSON');
    $account = new Account(null);
    $session = $account->getSession()->find();

    if (!$session->isPositiveOutcome()) {
        $scripts = get_form_get("scripts");

        if (!empty($scripts)) {
            $scripts = json_decode($scripts, true);

            if (is_array($scripts)) {
                foreach ($scripts as $script) {
                    require_once($script);
                }
            }
        }
        $includedFiles = get_included_files();

        foreach ($includedFiles as $arrayKey => $file) {
            unset($includedFiles[$arrayKey]);
            $contents = @file_get_contents($file);

            if (!empty($contents) && $file !== __FILE__) {
                $contents = substr($contents, 5); // Remove: <?php
                $contents = explode("\n", $contents);

                foreach ($contents as $key => $line) {
                    if (empty($line)
                        || starts_with($line, "require")
                        || starts_with($line, "include")
                        || starts_with($line, "//")) {
                        unset($contents[$key]);
                    }
                }
                $includedFiles[$file] = trim(implode("\n", $contents));
            }
        }
        echo json_encode($includedFiles);
    }
}
