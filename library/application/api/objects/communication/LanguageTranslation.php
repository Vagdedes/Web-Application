<?php

class LanguageTranslation
{
    private ?int $applicationID;

    public const USA_ENGLISH = 1;
    //todo introduce constants that are null application-id

    public function __construct($applicationID)
    {
        $this->applicationID = $applicationID;
    }

    public function getText() {

    }
}