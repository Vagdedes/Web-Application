<?php

// Here we add objects that are completely standalone

class Application
{
    private ?int $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getID(): ?int
    {
        return $this->id;
    }

    public function getAccount($id, $email = null, $identification = null, $checkDeletion = true): Account
    {
        return new Account($this->id, $id, $email, $identification, $checkDeletion);
    }

    public function getAccountRegistry($email, $password, $name): AccountRegistry
    {
        return new AccountRegistry($this->id, $email, $password, $name);
    }

    public function getWebsiteSession(): WebsiteSession
    {
        return new WebsiteSession($this->id);
    }

    public function getPaymentProcessor(): PaymentProcessor
    {
        return new PaymentProcessor($this->id);
    }

    public function getLanguageTranslation(): LanguageTranslation
    {
        return new LanguageTranslation($this->id);
    }

    public function getWebsiteKnowledge(): WebsiteKnowledge
    {
        return new WebsiteKnowledge($this->id);
    }
}