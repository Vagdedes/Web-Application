<?php

class HetznerLoadBalancer
{

    public string $name;
    public int $identifier;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $liveConnections;
    public bool $blockingAction;
    public array $targets;

    public function __construct(string                  $name,
                                int                     $identifier,
                                int                     $liveConnections,
                                HetznerLoadBalancerType $type,
                                HetznerServerLocation   $location,
                                HetznerNetwork          $network,
                                array                   $targets)
    {
        $this->name = $name;
        $this->identifier = $identifier;
        $this->liveConnections = $liveConnections;
        $this->location = $location;
        $this->type = $type;
        $this->blockingAction = false;
        $this->targets = $targets;
        $this->network = $network;
    }

    public function upgrade(int $level = -1): bool
    {
        // todo
        return false;
    }

    public function downgrade(): bool
    {
        // todo
        return false;
    }

    // Separator

    public function remove(): bool
    {
        // todo
        return false;
    }

    // Separator

    public function addTarget(HetznerServer $server): bool
    {
        if ($this->hasRemainingTargetSpace()
            && !$this->isTarget($server->identifier)
            && !$server->isInLoadBalancer()) {
            // todo
        }
        return false;
    }

    public function removeTarget(HetznerServer $server): bool
    {
        if ($this->isTarget($server->identifier)
            && $server->isInLoadBalancer()
            && $server->loadBalancer->identifier === $this->identifier) {
            // todo
        }
        return false;
    }

    // Separator

    public function getRemainingTargetSpace(): int
    {
        return $this->type->maxTargets - $this->targetCount();
    }

    public function hasRemainingTargetSpace(): int
    {
        return $this->getRemainingTargetSpace() > 0;
    }

    // Separator

    public function isTarget(string $serverID): int
    {
        return array_key_exists($serverID, $this->targets);
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
