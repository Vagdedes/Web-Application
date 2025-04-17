<?php

function transactions(int $pastDays): string
{
    require_once '/var/www/.structure/library/paypal/init.php';
    require_once '/var/www/.structure/library/stripe/init.php';
    $bool = update_paypal_storage(0, $pastDays, true);
    $bool |= update_stripe_storage();
    $account = new Account();
    $account->getPaymentProcessor()->run();
    return strval($bool);
}