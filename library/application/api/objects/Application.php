<?php

// Here we add objects that are not related to account-id and are therefore completely standalone

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

    public function getProduct($documentation = true, $productID = null): WebsiteProduct
    {
        return new WebsiteProduct($this->id, $documentation, $productID);
    }

    public function getProductGiveaway(): ProductGiveaway
    {
        return new ProductGiveaway($this->id);
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

    public function getWebsiteFunctionality($name, $account = null): WebsiteFunctionality
    {
        return new WebsiteFunctionality($this->id, $name, $account);
    }

    public function getWebsiteModeration($name): WebsiteModeration
    {
        return new WebsiteModeration($this->id, $name);
    }
}