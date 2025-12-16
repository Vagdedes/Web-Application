<?php

class __SchedulerTasks
{

    public static function php_async(int $limit): mixed
    {
        require_once '/var/www/.structure/library/base/async.php';
        $async = new PhpAsync();
        return $async->executeStored($limit);
    }

    public static function big_manage_schedulers(): mixed
    {
        require_once '/var/www/.structure/library/bigmanage/init.php';
        return BigManageCombinedScheduler::run();
    }

    public static function hetzner_maintain_network(): mixed
    {
        require_once '/var/www/.structure/library/hetzner/init.php';
        return hetzner_maintain_network();
    }

    public static function transactions(int $pastDays): mixed
    {
        require_once '/var/www/.structure/library/paypal/init.php';
        require_once '/var/www/.structure/library/stripe/init.php';
        $bool = update_paypal_storage(0, $pastDays, true, true);
        $bool |= update_stripe_storage();
        return strval($bool);
    }

}
