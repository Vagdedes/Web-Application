<?php

function get_stripe_object(): ?object
{
    $stripe_credentials = get_keys_from_file("/var/www/.structure/private/stripe_credentials", 1);
    return $stripe_credentials === null ? null : new \Stripe\StripeClient($stripe_credentials[0]);
}

function get_stripe_list($object): array
{
    return is_object($object)
    && isset($object->object) && $object->object == "list"
    && isset($object->data) ? $object->data : array();
}
