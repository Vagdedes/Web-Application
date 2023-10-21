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

    public function getAccount($id, $email = null, $username = null,
                               $identification = null,
                               $checkDeletion = true,
                               $cache = true): Account
    {
        return new Account($this->id, $id, $email, $username, $identification, $checkDeletion, $cache);
    }

    public function getAccountRegistry($email, $password, $name,
                                       $firstName = null, $middleName = null, $lastName = null,
                                       $discordWebhook = null): AccountRegistry
    {
        return new AccountRegistry($this->id, $email, $password, $name, $firstName, $middleName, $lastName, $discordWebhook);
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