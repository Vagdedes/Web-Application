<?php

class HetznerLoadBalancer
{

    public ?string $identifier;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $liveConnections;
    public bool $blockingAction;
    public array $targets;

    public function __construct(?string                 $identifier,
                                int                     $liveConnections,
                                HetznerLoadBalancerType $type,
                                HetznerServerLocation   $location,
                                HetznerNetwork          $network,
                                array                   $targets)
    {
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

    public function addTarget(HetznerServer $server): bool
    {
        if ($this->hasRemainingTargetSpace()
            && !$this->isTarget($server->identifier)
            && !$server->isInLoadBalancer()) {
            $this->targets[] = $server->identifier;
            return false;
        }
        return false;
    }

    public function removeTarget(HetznerServer $server): bool
    {
        if ($this->isTarget($server->identifier)
            && $server->isInLoadBalancer()
            && $server->loadBalancer->identifier === $this->identifier) {
            $this->targets[] = $server->identifier;
            return false;
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
