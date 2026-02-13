<?php

abstract class AbstractMethodReply
{

    private ?bool $success;

    public function __construct(?bool $success)
    {
        $this->success = $success;
    }

    public final function isPositiveOutcome(): bool
    {
        return $this->success === true;
    }

    public final function isNegativeOutcome(): bool
    {
        return $this->success !== true;
    }

    public final function isStopOutcome(): bool
    {
        return $this->success === null;
    }

    public final function setOutcome(?bool $outcome): void
    {
        $this->success = $outcome;
    }

}
