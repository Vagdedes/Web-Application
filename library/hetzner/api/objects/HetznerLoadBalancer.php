<?php

class HetznerLoadBalancer
{

    public string $name;
    public HetznerServerLocation $location;
    public HetznerLoadBalancerType $type;
    public HetznerNetwork $network;

    public function __construct(string                  $name,
                                HetznerServerLocation   $location,
                                HetznerLoadBalancerType $type,
                                HetznerNetwork          $network)
    {
        $this->name = $name;
        $this->location = $location;
        $this->type = $type;
        $this->network = $network;
    }
}
