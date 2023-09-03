<?php

if (isset($_GET["key1"]) && isset($_GET["key2"]) && isset($_GET["action"])) {
    $domains = array();

    if (isset($_SERVER["SERVER_NAME"]) && (sizeof($domains) == 0 || in_array($_SERVER["SERVER_NAME"], $domains))) {
        $single = true;

        if (isset($_GET["values"])) {
            $single = false;
        } else if (!isset($_GET["value"])) {
            exit();
        }

        // methods go here

        // Process

        $key1 = $_GET["key1"];
        $key2 = $_GET["key2"];

        if (isset($key1[0]) && isset($key2[0]) && $key1 !== $key2) {
            require_once '/var/www/.structure/library/base/utilities.php';
            require_once '/var/www/.structure/library/base/encrypt.php';
            // variables go here

            switch ($_GET["action"]) {
                case "encrypt":
                    if ($single) {
                        echo encrypt_ip($_GET["value"]);
                    } else {
                        $separator = " ";
                        $keyValue = $_GET["values"];
                        $values = "";

                        foreach (explode($separator, $keyValue) as $value) {
                            $values .= encrypt_ip($value) . $separator;
                        }
                        if (isset($values[0])) {
                            echo substr($values, 0, -1);
                        } else {
                            echo $keyValue;
                        }
                    }
                    break;
                case "decrypt":
                    if ($single) {
                        echo decrypt_ip($_GET["value"]);
                    } else {
                        $separator = " ";
                        $keyValue = $_GET["values"];
                        $values = "";

                        foreach (explode($separator, $keyValue) as $value) {
                            $values .= decrypt_ip($value) . $separator;
                        }
                        if (isset($values[0])) {
                            echo substr($values, 0, -1);
                        } else {
                            echo $keyValue;
                        }
                    }
                    break;
                default:
                    exit();
            }
        } else {
            exit();
        }
    } else {
        exit();
    }
} else {
    exit();
}
