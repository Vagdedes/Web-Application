<?php

class HetznerServer
{

    public ?string $name;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public ?HetznerLoadBalancer $loadBalancer;
    public HetznerServerLocation $location;
    public bool $backups;
    public int $customStorageGB;
    public bool $blockingAction;

    public function __construct(?string               $name,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                HetznerServerLocation $location,
                                bool                  $backups,
                                int                   $customStorageGB,
                                bool                  $blockingAction)
    {
        $this->name = $name;
        $this->cpuPercentage = $cpuPercentage;
        $this->type = $type;
        $this->location = $location;
        $this->loadBalancer = $loadBalancer;
        $this->backups = $backups;
        $this->customStorageGB = $customStorageGB;
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
