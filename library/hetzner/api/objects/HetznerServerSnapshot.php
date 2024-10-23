<?php

class HetznerServerSnapshot
{

    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

}
