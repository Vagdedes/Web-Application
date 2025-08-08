<?php

if (file_exists('/root/vendor/autoload.php')) {
    require_once '/root/vendor/autoload.php';
} else {
    require_once '/var/www/vendor/autoload.php';
}
