<?php

class HetznerNetwork
{

    public string $name;
    public HetznerServerLocation $location;

    public function __construct(string                $name,
                                HetznerServerLocation $location)
    {
        $this->name = $name;
        $this->location = $location;
    }

}
