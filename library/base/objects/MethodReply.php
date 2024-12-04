<?php

require_once '/var/www/.structure/library/base/objects/AbstractMethodReply.php';

class MethodReply extends AbstractMethodReply
{

    private ?string $message;
    private mixed $object;

    public function __construct(bool $success, ?string $message = null, mixed $object = null)
    {
        parent::__construct($success);
        $this->message = $message;
        $this->object = $object;
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
