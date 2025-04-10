<?php

class AIFieldObject
{

    private string $type;
    private ?int $maxLength;
    private bool $isNullable;
    private string $definition;
    private array $parents;

    public function __construct(
        string $type,
        ?int   $maxLength,
        bool   $isNullable,
        string $definition,
    )
    {
        $this->type = $type;
        $this->maxLength = $maxLength;
        $this->isNullable = $isNullable;
        $this->definition = $definition;
        $this->parents = [];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMaxLength(): int|string
    {
        return $this->maxLength ?? "Infinity";
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getParents(): array
    {
        return $this->parents;
    }

    public function addParent(string $parent): void
    {
        $this->parents[] = $parent;
    }

    public function addParents(array $parents): void
    {
        foreach ($parents as $parent) {
            $this->addParent($parent);
        }
    }

}
