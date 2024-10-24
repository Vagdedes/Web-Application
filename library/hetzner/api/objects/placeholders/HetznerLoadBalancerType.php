<?php

class HetznerLoadBalancerType
{

    public string $name;
    public int $targets, $maxConnections;
    public float $pricePerHour;

    public function __construct(string $name,
                                int    $targets,
                                int    $maxConnections,
                                float  $pricePerHour)
    {
        $this->name = $name;
        $this->targets = $targets;
        $this->maxConnections = $maxConnections;
        $this->pricePerHour = $pricePerHour;
    }

}
