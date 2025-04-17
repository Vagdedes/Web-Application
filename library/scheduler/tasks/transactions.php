<?php

function transactions(int $pastDays): string
{
    $bool = update_paypal_storage(0, $pastDays, true);
    $bool |= update_stripe_storage();
    $account = new Account();
    $account->getPaymentProcessor()->run();
    return strval($bool);
}