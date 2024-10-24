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

    public function __construct(?string               $name,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                ?HetznerNetwork       $network,
                                HetznerServerLocation $location,
                                bool                  $backups)
    {
        $this->name = $name;
        $this->cpuPercentage = $cpuPercentage;
        $this->type = $type;
        $this->location = $location;
        $this->loadBalancer = $loadBalancer;
        $this->network = $network;
        $this->backups = $backups;
    }

    public function getPricePerHour(): float
    {
        if ($this->backups) {
            return $this->type->pricePerHour * HetznerVariables::HETZNER_BACKUP_PRICE_MULTIPLIER;
        } else {
            return $this->type->pricePerHour;
        }
    }

}
