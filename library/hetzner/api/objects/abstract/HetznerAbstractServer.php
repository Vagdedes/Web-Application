<?php

abstract class HetznerAbstractServer
{

    public string $name;
    public int $cpuCores;
    public int $memoryGB;
    public int $storageGB;
    public float $pricePerHour;

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB, float $pricePerHour)
    {
        $this->name = $name;
        $this->cpuCores = $cpuCores;
        $this->memoryGB = $memoryGB;
        $this->storageGB = $storageGB;
        $this->pricePerHour = $pricePerHour;
    }

    public function maxCpuPercentage(): float
    {
        return $this->cpuCores * 100.0;
    }

}
