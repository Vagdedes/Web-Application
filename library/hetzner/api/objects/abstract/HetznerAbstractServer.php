<?php

abstract class HetznerAbstractServer
{

    public string $name;
    public int $cpuCores, $memoryGB, $storageGB;

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB)
    {
        $this->name = $name;
        $this->cpuCores = $cpuCores;
        $this->memoryGB = $memoryGB;
        $this->storageGB = $storageGB;
    }

    public function maxCpuPercentage(): float
    {
        return $this->cpuCores * 100.0;
    }

}
