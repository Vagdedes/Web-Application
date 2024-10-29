<?php

class HetznerNetwork
{

    public string $identifier;
    public array $servers, $loadBalancers;

    public function __construct(string $identifier, array $servers, array $loadBalancers)
    {
        $this->identifier = $identifier;
        $this->servers = $servers;
        $this->loadBalancers = $loadBalancers;
    }

    public function isServerIncluded(string $id): bool
    {
        return in_array($id, $this->servers);
    }

    public function isLoadBalancerIncluded(string $id): bool
    {
        return in_array($id, $this->loadBalancers);
    }

}
