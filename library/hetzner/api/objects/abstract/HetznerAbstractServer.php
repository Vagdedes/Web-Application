<?php

abstract class HetznerAbstractServer
{

    public string $name;
    public int $cpuCores;
    public int $memoryGB;

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB)
    {
        $this->name = $name;
        $this->cpuCores = $cpuCores;
        $this->memoryGB = $memoryGB;
    }

    public function maxCpuPercentage(): float
    {
        return $this->cpuCores * 100.0;
    }

}
