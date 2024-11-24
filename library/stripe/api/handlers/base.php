<?php

function get_stripe_object(int $account = 0): ?object
{
    $stripe_credentials = get_keys_from_file(StripeVariables::CREDENTIALS_DIRECTORY, StripeVariables::ACCOUNT_AMOUNT);
    return $stripe_credentials === null ? null : new \Stripe\StripeClient($stripe_credentials[$account]);
}

function get_stripe_list(mixed $object): array
{
    return is_object($object)
    && isset($object->object) && $object->object == "list"
    && isset($object->data) ? $object->data : array();
}
