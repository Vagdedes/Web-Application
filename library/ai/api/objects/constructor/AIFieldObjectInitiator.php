<?php

class AIFieldObjectInitiator
{

    private AIFieldObject $type;
    private string $name, $definition;

    public function __construct(
        AIFieldObject $type,
        string        $name,
        string        $definition,
    )
    {
        $this->type = $type;
        $this->name = $name;
        $this->definition = $definition;
    }

    public function getType(): AIFieldObject
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

}
