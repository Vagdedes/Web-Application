<?php

class HetznerNetwork
{

    private int $identifier;
    private array $servers;

    public function __construct(int $identifier, array $servers)
    {
        $this->identifier = $identifier;
        $this->servers = $servers;
    }

    public function getIdentifier(): int
    {
        return $this->identifier;
    }

    public function isServerIncluded(string $id): bool
    {
        return in_array($id, $this->servers);
    }

}
