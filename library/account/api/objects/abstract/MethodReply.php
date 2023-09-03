<?php

class MethodReply
{

    private bool $success;
    private $message, $object;

    public function __construct($success, $message = null, $object = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->object = $object;
    }

    public function isPositiveOutcome(): bool
    {
        return $this->success;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getObject()
    {
        return $this->object;
    }
}
