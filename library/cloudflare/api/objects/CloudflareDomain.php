<?php

class CloudflareDomain
{

    private string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $domain;
    }

    public function getZone(): ?string
    {
        return CloudflareVariables::CLOUDFLARE_ZONES[$this->domain] ?? null;
    }

    public function getDNS(int $page = 1): array
    {
        $zone = self::getZone();

        if ($zone === null) {
            return array();
        }
        $array = array();
        return $array;
    }

    public function add_A_DNS(string $name, string $target, bool $proxied): bool
    {
        $zone = self::getZone();

        if ($zone === null) {
            return false;
        }
        $data = [
            'type' => 'A',
            'name' => $name,
            'content' => $target,
            'ttl' => 1, // 1 is Auto
            'proxied' => $proxied
        ];
        $request = CloudflareConnection::query(
            HetznerConnectionType::POST,
            "zones/" . $zone . "/dns_records",
            json_encode($data)
        );
        return $request?->success ?? false;
    }

    public function removeA_DNS(string $name): bool
    {
        $credentials = CloudflareConnection::getAPIKey();

        if ($credentials === null) {
            return false;
        }
        $zone = self::getZone();

        if ($zone === null) {
            return false;
        }
        $dns = self::getDNS();

        if (!empty($dns)) {
            foreach ($dns as $page) {
                foreach ($page->result as $record) {
                    var_dump($record);
                }
            }
        }
        return false;
    }
}
