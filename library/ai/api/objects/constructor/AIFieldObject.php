<?php

class AIFieldObject
{

    private string $type;
    private ?int $maxLength;
    private bool $isNullable;
    private string $definition;
    private array $parents;
    private ?array $enums;
    private mixed $default;

    public function __construct(
        string|array $type,
        ?int         $maxLength,
        bool         $isNullable,
        string       $definition,
        ?array       $enums = null,
        mixed        $default = null
    )
    {
        $this->type = is_array($type)
            ? implode("|", $type)
            : $type;
        $this->maxLength = $maxLength;
        $this->isNullable = $isNullable;
        $this->definition = $definition;
        $this->enums = $enums;
        $this->default = $default;
        $this->parents = [];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMaxLength(bool $notNull = true): int|string|null
    {
        return $this->maxLength ?? ($notNull ? "INFINITY" : null);
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getEnums(): ?array
    {
        return $this->enums;
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
