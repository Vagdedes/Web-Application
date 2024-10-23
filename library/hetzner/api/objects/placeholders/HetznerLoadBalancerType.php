<?php

class HetznerLoadBalancerType
{

    public string $name;
    public int $targets, $maxConnections;

    public function __construct(string $name,
                                int    $targets,
                                int    $maxConnections)
    {
        $this->name = $name;
        $this->targets = $targets;
        $this->maxConnections = $maxConnections;
    }

}
