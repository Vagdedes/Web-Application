<?php

class WebsiteKnowledge
{
    private ?int $applicationID;

    public function __construct($applicationID)
    {
        $this->applicationID = $applicationID;
    }
}