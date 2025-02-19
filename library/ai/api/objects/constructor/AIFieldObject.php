<?php

class AIFieldObject {

    private string $type;
    private ?int $byteSize;
    private ?int $length;
    private bool $isNullable;
    private bool $failIfNotFound;

    public function __construct(
        string $type,
        ?int $byteSize,
        ?int $length,
        bool $isNullable,
        bool $failIfNotFound
    )
    {
        $this->type = $type;
        $this->byteSize = $byteSize;
        $this->length = $length;
        $this->isNullable = $isNullable;
        $this->failIfNotFound = $failIfNotFound;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getByteSize(): ?int
    {
        return $this->byteSize;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function failIfNotFound(): bool
    {
        return $this->failIfNotFound;
    }

}
