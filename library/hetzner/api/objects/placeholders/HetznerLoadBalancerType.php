<?php

class HetznerLoadBalancerType
{

    public string $name;
    public int $maxTargets, $maxConnections;

    public function __construct(string $name,
                                int    $targets,
                                int    $maxConnections)
    {
        $this->name = $name;
        $this->maxTargets = $targets;
        $this->maxConnections = $maxConnections;
    }

}
