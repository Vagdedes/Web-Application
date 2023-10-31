<?php

// Here we add objects that are completely standalone

class Application
{
    private ?int $id;

    public const
        LOAD_BALANCER_IP = "10.0.0.3",
        IMAGES_PATH = "https://vagdedes.com/.images/",
        WEBSITE_DESIGN_PATH = "https://vagdedes.com/.css/",
        DOWNLOADS_PATH = "/var/www/vagdedes/.temporary/";

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
        return new Account(
            $this->id,
            $id,
            $email,
            $username,
            $identification,
            $checkDeletion,
            $cache
        );
    }

    public function getAccountRegistry(?string         $email, ?string $password, ?string $name,
                                       ?string         $firstName = null, ?string $middleName = null, ?string $lastName = null,
                                       ?AccountSession $session = null,
                                       ?string         $discordWebhook = AccountRegistry::DEFAULT_WEBHOOK): AccountRegistry
    {
        return new AccountRegistry(
            $this->id,
            $email,
            $password,
            $name,
            $firstName,
            $middleName,
            $lastName,
            $session,
            $discordWebhook
        );
    }

    public function getAccountAmount(): int
    {
        return AccountRegistry::getAccountAmount($this->id);
    }

    public function getAccountSession(): AccountSession
    {
        return new AccountSession($this->id);
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