<?php
$stripe_account_amount = 2;

function get_stripe_object(int $account = 0): ?object
{
    global $stripe_account_amount, $stripe_credentials_directory;
    $stripe_credentials = get_keys_from_file($stripe_credentials_directory, $stripe_account_amount);
    return $stripe_credentials === null ? null : new \Stripe\StripeClient($stripe_credentials[$account]);
}

function get_stripe_list(mixed $object): array
{
    return is_object($object)
    && isset($object->object) && $object->object == "list"
    && isset($object->data) ? $object->data : array();
}
