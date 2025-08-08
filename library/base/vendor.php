<?php

if (file_exists('/var/www/vendor/autoload.php')) {
    require_once '/var/www/vendor/autoload.php';
} else if (file_exists('/root/vendor/autoload.php')) {
    require_once '/root/vendor/autoload.php';
}
