<?php
$stripe_account_amount = 2;

function get_stripe_object($account = 0): ?object
{
    global $stripe_account_amount;
    $stripe_credentials = get_keys_from_file("/var/www/.structure/private/stripe_credentials", $stripe_account_amount);
    return $stripe_credentials === null ? null : new \Stripe\StripeClient($stripe_credentials[$account]);
}

function get_stripe_list($object): array
{
    return is_object($object)
    && isset($object->object) && $object->object == "list"
    && isset($object->data) ? $object->data : array();
}
