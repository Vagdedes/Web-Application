<?php

class GameCloudUser
{
    private ?int $platform, $license;
    private GameCloudActions $actions;
    private GameCloudInformation $information;
    private GameCloudVerification $verification;
    private GameCloudEmail $email;

    public function __construct($platform, $license)
    {
        $this->platform = $platform;
        $this->license = $license;
        $this->actions = new GameCloudActions($this);
        $this->information = new GameCloudInformation($this);
        $this->verification = new GameCloudVerification($this);
        $this->email = new GameCloudEmail($this);
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

    public function setPlatform(int|string $platform): void
    {
        $this->platform = $platform;
    }

    public function setLicense(int|string $license): void
    {
        $this->license = $license;
    }

    public function getActions(): GameCloudActions
    {
        return $this->actions;
    }

    public function getInformation(): GameCloudInformation
    {
        return $this->information;
    }

    public function getVerification(): GameCloudVerification
    {
        return $this->verification;
    }

    public function getEmail(): GameCloudEmail
    {
        return $this->email;
    }

    public function clearMemory(mixed $key = null, ?callable $callable = null): void
    {
        if ($this->isValid()) {
            $potentialKeys = 2;

            if ($key === null) {
                clear_memory(
                    array(array(
                        get_sql_cache_key("platform_id", $this->platform),
                        get_sql_cache_key("license_id", $this->license)
                    )),
                    true,
                    $potentialKeys,
                    $callable
                );
            } else if (is_array($key)) {
                $key1 = get_sql_cache_key("platform_id", $this->platform);
                $key2 = get_sql_cache_key("license_id", $this->license);

                foreach ($key as $item) {
                    clear_memory(
                        array(array($item, $key1, $key2)),
                        true,
                        $potentialKeys,
                        $callable
                    );
                }
            } else {
                clear_memory(
                    array(array(
                        $key,
                        get_sql_cache_key("platform_id", $this->platform),
                        get_sql_cache_key("license_id", $this->license)
                    )),
                    true,
                    $potentialKeys,
                    $callable
                );
            }
        } else if ($key !== null) {
            clear_memory(array($key), true, 1);
        }
    }
}
