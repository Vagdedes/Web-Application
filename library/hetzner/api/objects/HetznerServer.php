<?php

class HetznerServer
{

    public string $name;
    public int $identifier;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public ?HetznerLoadBalancer $loadBalancer;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $customStorageGB;
    public bool $blockingAction;

    public function __construct(string                $name,
                                int                   $identifier,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                HetznerServerLocation $location,
                                HetznerNetwork        $network,
                                int                   $customStorageGB,
                                bool                  $blockingAction)
    {
        $this->name = $name;
        $this->identifier = $identifier;
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
        $level = HetznerComparison::getServerLevel($this) + 1;

        if ($this instanceof HetznerArmServer) {
            global $HETZNER_ARM_SERVERS;

            if ($level <= sizeof($HETZNER_ARM_SERVERS)) {
                $level = $HETZNER_ARM_SERVERS[$level];
            }
        } else {
            global $HETZNER_X86_SERVERS;

            if ($level <= sizeof($HETZNER_X86_SERVERS)) {
                $level = $HETZNER_X86_SERVERS[$level];
            }
        }
        return false;
    }

    public function downgrade(): bool
    {
        $level = HetznerComparison::getServerLevel($this);

        if ($level > 0) {
            $level -= 1;

            if ($this instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;
            } else {
                global $HETZNER_X86_SERVERS;
            }
        }
        return false;
    }

    // Separator

    public function update(int $snapshot): bool
    {
        return false;
    }

    public function remove(): bool
    {
        return self::executedAction(
            get_hetzner_object_pages(
                HetznerConnectionType::DELETE,
                "servers/" . $this->identifier,
                null,
                false
            )
        );
    }

    // Separator

    public function isInLoadBalancer(): bool
    {
        return $this->loadBalancer !== null;
    }

    public function attachToLoadBalancers(array $loadBalancers): bool
    {
        while (true) {
            $loadBalancer = HetznerComparison::findLeastPopulatedLoadBalancer($loadBalancers);

            if ($loadBalancer === null) {
                break;
            }
            if ($loadBalancer->addTarget($this)) {
                return true;
            } else {
                unset($loadBalancers[$loadBalancer->identifier]);
            }
        }
        return false;
    }

}
