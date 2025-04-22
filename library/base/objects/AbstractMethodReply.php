<?php

abstract class AbstractMethodReply
{

    private bool $success;

    public function __construct(bool $success)
    {
        $this->success = $success;
    }

    public final function isPositiveOutcome(): bool
    {
        return $this->success;
    }

    public final function setOutcome(bool $outcome): void
    {
        $this->success = $outcome;
    }

}
