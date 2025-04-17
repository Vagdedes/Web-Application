<?php

function application_big_manage_combined_scheduler(): bool
{
    require_once '/var/www/.structure/library/bigmanage/init.php';
    return BigManageCombinedScheduler::run();
}