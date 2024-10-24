<?php

class HetznerServer
{

    public ?string $name;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public ?HetznerLoadBalancer $loadBalancer;
    public HetznerServerLocation $location;
    public ?HetznerNetwork $network;
    public bool $backups;
    public int $customStorageGB;
    public bool $blockingAction;

    public function __construct(?string               $name,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                ?HetznerNetwork       $network,
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
        $this->network = $network;
        $this->backups = $backups;
        $this->customStorageGB = $customStorageGB;
        $this->blockingAction = $blockingAction;
    }

}
