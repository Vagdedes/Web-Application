<?php

class HetznerLoadBalancer
{

    public ?string $name;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public ?HetznerNetwork $network;
    public int $liveConnections;

    public function __construct(?string                 $name,
                                int                     $liveConnections,
                                HetznerLoadBalancerType $type,
                                ?HetznerNetwork         $network,
                                HetznerServerLocation   $location)
    {
        $this->name = $name;
        $this->liveConnections = $liveConnections;
        $this->location = $location;
        $this->type = $type;
        $this->network = $network;
    }

}
