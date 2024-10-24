<?php

class HetznerLoadBalancer
{

    public ?string $name;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public int $liveConnections;
    public bool $blockingAction;

    public function __construct(?string                 $name,
                                int                     $liveConnections,
                                HetznerLoadBalancerType $type,
                                HetznerServerLocation   $location,
                                bool                    $blockingAction)
    {
        $this->name = $name;
        $this->liveConnections = $liveConnections;
        $this->location = $location;
        $this->type = $type;
        $this->blockingAction = $blockingAction;
    }

    public function upgrade(): bool
    {
        return false;
    }

    public function downgrade(): bool
    {
        return false;
    }

    public function remove(): bool
    {
        return false;
    }

}
