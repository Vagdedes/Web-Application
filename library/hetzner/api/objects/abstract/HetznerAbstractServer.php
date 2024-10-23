<?php

abstract class HetznerAbstractServer
{

    public string $name;
    public int $cpuCores;
    public int $memoryGB;
    public int $storageGB;

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB)
    {
        $this->name = $name;
        $this->cpuCores = $cpuCores;
        $this->memoryGB = $memoryGB;
        $this->storageGB = $storageGB;
    }

}
