<?php

class HetznerServer
{

    public ?string $name, $ipv4, $ipv6, $local;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public ?HetznerLoadBalancer $loadBalancer;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $customStorageGB;
    public bool $blockingAction;

    public function __construct(?string               $name,
                                ?string               $ipv4,
                                ?string               $ipv6,
                                ?string               $local,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                HetznerServerLocation $location,
                                HetznerNetwork        $network,
                                int                   $customStorageGB,
                                bool                  $blockingAction)
    {
        $this->name = $name;
        $this->ipv4 = $ipv4;
        $this->ipv6 = $ipv6;
        $this->local = $local;
        $this->cpuPercentage = $cpuPercentage;
        $this->network = $network;
        $this->type = $type;
        $this->location = $location;
        $this->loadBalancer = $loadBalancer;
        $this->customStorageGB = $customStorageGB;
        $this->blockingAction = $blockingAction;
    }

    // Separator

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

    // Separator

    public function isInLoadBalancer(array $loadBalancers): bool
    {
        foreach ($loadBalancers as $loadBalancer) {
            if ($loadBalancer->hasIP($this->ipv4)
                || $loadBalancer->hasIP($this->ipv6)
                || $loadBalancer->hasIP($this->local)) {
                return true;
            }
        }
        return false;
    }

    public function attachToLoadBalancers(array $loadBalancers): bool
    {
        return false;
    }

}
