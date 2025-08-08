<?php

if (file_exists('/var/www/vendor/autoload.php')) {
    require_once '/var/www/vendor/autoload.php';
} else {
    require_once '/root/vendor/autoload.php';
}
