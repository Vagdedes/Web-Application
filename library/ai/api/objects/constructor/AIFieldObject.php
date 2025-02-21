<?php

class AIFieldObject
{

    private string $type;
    private ?int $length;
    private bool $isNullable;
    private bool $canFail;

    public function __construct(
        string $type,
        ?int   $length,
        bool   $isNullable,
        bool   $canFail
    )
    {
        $this->type = $type;
        $this->length = $length;
        $this->isNullable = $isNullable;
        $this->canFail = $canFail;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function canFail(): bool
    {
        return $this->canFail;
    }

}
