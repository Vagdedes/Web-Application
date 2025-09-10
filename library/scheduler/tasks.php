<?php

class __SchedulerTasks
{

    public static function php_async(int $limit): int
    {
        require_once '/var/www/.structure/library/base/async.php';
        ini_set('error_log', '/var/log/apache2/error.log');
        $async = new PhpAsync();
        return $async->executeStored($limit);
    }

    public static function big_manage_schedulers(): bool
    {
        require_once '/var/www/.structure/library/bigmanage/init.php';
        return BigManageCombinedScheduler::run();
    }

    public static function hetzner_maintain_network(): bool
    {
        require_once '/var/www/.structure/library/hetzner/init.php';
        return hetzner_maintain_network();
    }

    public static function transactions(int $pastDays): string
    {
        require_once '/var/www/.structure/library/account/init.php';
        require_once '/var/www/.structure/library/paypal/init.php';
        require_once '/var/www/.structure/library/stripe/init.php';
        $bool = update_paypal_storage(0, $pastDays, true);
        $bool |= update_stripe_storage();
        $account = new Account();
        $account->getPaymentProcessor()->run();
        return strval($bool);
    }
}
