<?php

class HetznerLoadBalancerType
{

    public string $type;
    public int $targets, $maxConnections;

    public function __construct(string $type,
                                int    $targets,
                                int    $maxConnections)
    {
        $this->type = $type;
        $this->targets = $targets;
        $this->maxConnections = $maxConnections;
    }
}
