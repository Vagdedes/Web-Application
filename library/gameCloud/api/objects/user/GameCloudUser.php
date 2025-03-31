<?php

class GameCloudUser
{
    private ?int $platform, $license;
    private GameCloudActions $actions;
    private GameCloudAccount $account;
    private GameCloudVerification $verification;
    private GameCloudPurchases $purchases;

    public function __construct(?int $platform, ?int $license)
    {
        $this->platform = $platform;
        $this->license = $license;
        $this->actions = new GameCloudActions($this);
        $this->account = new GameCloudAccount($this);
        $this->verification = new GameCloudVerification($this);
        $this->purchases = new GameCloudPurchases($this);
    }

    public function getPurchases(): GameCloudPurchases
    {
        return $this->purchases;
    }

    public function getPlatform(): ?int
    {
        return $this->platform;
    }

    public function getLicense(): ?int
    {
        return $this->license;
    }

    public function isValid(): bool
    {
        return $this->platform !== null
            && $this->platform > 0
            && $this->license !== null
            && $this->license > 0;
    }

    public function setPlatform(int|string $platform): int|string
    {
        $this->platform = $platform;
        return $platform;
    }

    public function setLicense(int|string $license): int|string
    {
        $this->license = $license;
        return $license;
    }

    public function getActions(): GameCloudActions
    {
        return $this->actions;
    }

    public function getAccount(): GameCloudAccount
    {
        return $this->account;
    }

    public function getVerification(): GameCloudVerification
    {
        return $this->verification;
    }

}
