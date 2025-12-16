<?php
require '/var/www/.structure/library/base/form.php';
$path = get_form_get("path");

if (!empty($path)) {
    require '/var/www/.structure/library/base/requirements/account_systems.php';
    $account = new Account();
    $session = $account->getSession()->find();

    if ($session->isPositiveOutcome()) {
        if ($session->getObject()->getPermissions()->hasPermission(
            "view.path." . str_replace("/", ".", $path)
        )) {
            set_communication_key(
                "session_account_id",
                $session->getObject()->getDetail("id")
            );
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
                header('Content-type: Application/JSON');
            }
            echo $contents;
        }
    } else {
        include '/var/www/idealistic/account/profile/index.php';
    }
} else if (is_private_connection()) {
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
            $contents = timed_file_get_contents($file, 3);

            if (!empty($contents) && $file !== __FILE__) {
                $contents = substr($contents, 5); // Remove: <?php

                if (str_ends_with($contents, "?>")) {
                    $contents = substr($contents, 0, -2); // Remove php end
                }
                $contents = explode("\n", $contents);

                foreach ($contents as $key => $line) {
                    if (str_starts_with($line, "require")
                        || str_starts_with($line, "include")) {
                        unset($contents[$key]);
                    }
                }
                $contents = trim(implode("\n", $contents));

                if (strlen($contents) > 0) {
                    $includedFiles[$file] = $contents;
                }
            }
        }
        echo json_encode($includedFiles);
    }
}
