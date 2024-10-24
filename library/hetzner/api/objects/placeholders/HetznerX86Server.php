<?php

class HetznerX86Server extends HetznerAbstractServer
{

    public function __construct(string $name, int $cpuCores, int $memoryGB, int $storageGB)
    {
        parent::__construct($name, $cpuCores, $memoryGB, $storageGB);
    }

}
