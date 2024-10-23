<?php

class HetznerServer
{

    public ?string $name;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public ?HetznerLoadBalancer $loadBalancer;
    public HetznerServerLocation $location;
    public ?HetznerNetwork $network;

    public function __construct(?string               $name,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                ?HetznerNetwork       $network,
                                HetznerServerLocation $location)
    {
        $this->name = $name;
        $this->cpuPercentage = $cpuPercentage;
        $this->type = $type;
        $this->location = $location;
        $this->loadBalancer = $loadBalancer;
        $this->network = $network;
    }

}
