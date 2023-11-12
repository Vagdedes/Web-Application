<?php

class MethodReply
{

    private bool $success;
    private $message, $object;

    public function __construct(bool $success, ?string $message = null, mixed $object = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->object = $object;
    }

    public function isPositiveOutcome(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getObject(): mixed
    {
        return $this->object;
    }
}
