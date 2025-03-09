<?php

abstract class HetznerAbstractServer
{

    private string $name;
    private int $cpuCores, $memoryGB, $storageGB;

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB)
    {
        $this->name = $name;
        $this->cpuCores = $cpuCores;
        $this->memoryGB = $memoryGB;
        $this->storageGB = $storageGB;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCpuCores(): int
    {
        return $this->cpuCores;
    }

    public function getMemoryGB(): int
    {
        return $this->memoryGB;
    }

    public function getStorageGB(): int
    {
        return $this->storageGB;
    }

    public function maxCpuPercentage(): float
    {
        return $this->cpuCores * 100.0;
    }



}
