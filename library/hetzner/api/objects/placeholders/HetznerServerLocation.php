<?php

class HetznerServerLocation
{

    public string $name, $networkZone;

    public function __construct(string $name, string $networkZone)
    {
        $this->name = $name;
        $this->networkZone = $networkZone;
    }

}
