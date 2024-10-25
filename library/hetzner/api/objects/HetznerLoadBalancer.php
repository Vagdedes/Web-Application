<?php

class HetznerLoadBalancer
{

    public ?string $name;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $liveConnections;
    public bool $blockingAction;
    public array $targets;

    public function __construct(?string                 $name,
                                int                     $liveConnections,
                                HetznerLoadBalancerType $type,
                                HetznerServerLocation   $location,
                                bool                    $blockingAction,
                                array                   $targets)
    {
        $this->name = $name;
        $this->liveConnections = $liveConnections;
        $this->location = $location;
        $this->type = $type;
        $this->blockingAction = $blockingAction;
        $this->targets = $targets;
    }

    public function upgrade(): bool
    {
        return false;
    }

    public function downgrade(): bool
    {
        return false;
    }

    // Separator

    public function remove(): bool
    {
        return false;
    }

    // Separator

    public function hasIP(string $ip): int
    {
        return in_array($ip, $this->targets);
    }

    public function targetCount(): int
    {
        return count($this->targets);
    }

    public function isFull(): bool
    {
        return $this->targetCount() >= $this->type->maxTargets;
    }

    public function isEmpty(): bool
    {
        return $this->targetCount() === 0;
    }

}
