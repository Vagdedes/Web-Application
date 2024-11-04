<?php

class CloudflareDomain
{

    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function add_A_DNS(string $name, string $target, bool $proxied): bool
    {
        return false;
    }

    public function removeA_DNS(string $name): bool
    {
        return false;
    }
}
