<?php

class HetznerArmServer extends HetznerAbstractServer
{

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB, float $pricePerHour)
    {
        parent::__construct($name, $cpuCores, $memoryGB, $storageGB, $pricePerHour);
    }

}
