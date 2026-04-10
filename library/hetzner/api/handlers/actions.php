<?php

class HetznerAction
{
    private static ?int $defaultImage = null;
    private static ?CloudflareDomain $defaultDomain = null;

    private static function date(string $time = "now"): string
    {
        return urlencode((new DateTimeImmutable($time))->format(DateTimeInterface::ATOM));
    }

    public static function executedAction(array|object|bool|null $query): bool
    {
        $action = is_array($query) ? ($query[0]?->action ?? null) : ($query?->action ?? null);
        return isset($action->error->code) || isset($action->error->message);
    }

    // Separator

    public static function getDefaultImage(): ?int
    {
        if (self::$defaultImage !== null) {
            return self::$defaultImage;
        }

        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "images") ?: [];

        foreach ($query as $page) {
            foreach ($page->images ?? [] as $image) {
                if ($image->description === HetznerVariables::HETZNER_DEFAULT_IMAGE_NAME) {
                    return self::$defaultImage = $image->id;
                }
            }
        }
        return null;
    }

    public static function getDefaultDomain(): ?CloudflareDomain
    {
        if (self::$defaultDomain !== null) {
            return self::$defaultDomain;
        }

        global $backup_domain;
        $parts = explode(".", $backup_domain ?? "");

        if (count($parts) >= 2) {
            return self::$defaultDomain = new CloudflareDomain(implode(".", array_slice($parts, -2)));
        }
        return null;
    }

    // Separator

    public static function getServers(?array $networks): array
    {
        if (empty($networks)) {
            return [];
        }

        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "servers") ?: [];
        $array = [];

        foreach ($query as $page) {
            foreach ($page->servers ?? [] as $server) {
                $serverID = $server->id;

                $metricsUrl = sprintf(
                    "servers/%s/metrics?type=cpu&start=%s&end=%s&step=%d",
                    $serverID,
                    self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds"),
                    self::date(),
                    HetznerVariables::CPU_METRICS_PAST_SECONDS
                );

                $metricsData = get_hetzner_object(HetznerConnectionType::GET, $metricsUrl);

                if (empty($metricsData)) {
                    return [];
                }

                $metrics = $metricsData->metrics?->time_series?->cpu?->values[0][1] ?? 0.0;

                foreach ($networks as $network) {
                    if (!$network->isServerIncluded($serverID) || !HetznerComparison::shouldConsiderServer($server->name)) {
                        continue;
                    }

                    $type = $server->server_type;
                    $isArm = strtolower($type->architecture) === "arm";
                    $serverClass = $isArm ? HetznerArmServer::class : HetznerX86Server::class;

                    $serverInstance = new $serverClass(
                        strtolower($type->name),
                        $type->cores,
                        $type->memory,
                        $type->disk
                    );

                    $array[$serverID] = new HetznerServer(
                        $server->name,
                        $server->public_net->ipv4->ip,
                        $serverID,
                        $metrics,
                        $serverInstance,
                        new HetznerServerLocation($server->datacenter->location->name),
                        $network,
                        $server->primary_disk_size,
                        $server->locked,
                        $server->image->status === "available" && $server->image->description === HetznerVariables::HETZNER_DEFAULT_IMAGE_NAME
                    );
                    break;
                }
            }
        }
        return $array;
    }

    public static function getNetworks(): array
    {
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "networks") ?: [];
        $array = [];

        foreach ($query as $page) {
            foreach ($page->networks ?? [] as $network) {
                $array[$network->id] = new HetznerNetwork($network->id, $network->servers);
            }
        }
        return $array;
    }

    // Separator

    public static function addNewServerBasedOn(
        array                 $servers,
        HetznerServerLocation $location,
        HetznerNetwork        $network,
        HetznerAbstractServer $serverType,
        int                   $level
    ): bool
    {
        $image = self::getDefaultImage();
        if ($image === null) {
            return false;
        }

        $existingNames = array_column($servers, 'name');

        do {
            $name = HetznerVariables::HETZNER_SERVER_NAME_PATTERN . random_int(100000, 999999);
        } while (in_array($name, $existingNames, true));

        $globalServers = $serverType instanceof HetznerArmServer ? $GLOBALS['HETZNER_ARM_SERVERS'] : $GLOBALS['HETZNER_X86_SERVERS'];

        $payload = [
            'name' => $name,
            'location' => $location->getName(),
            'image' => $image,
            'start_after_create' => true,
            'networks' => [$network->getIdentifier()],
            'server_type' => $globalServers[$level]->getName()
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return self::executedAction(
            get_hetzner_object(HetznerConnectionType::POST, "servers", $json)
        );
    }

    // Separator

    public static function maintain(array $servers): bool
    {
        $quit = false;

        foreach ($servers as $server) {
            if ($server instanceof HetznerServer && $server->isBlockingAction()) {
                $server->removeDnsRecords();
                $quit = true;
            }
        }

        if ($quit) {
            return true;
        }

        if (count($servers) > 1 && ($image = self::getDefaultImage()) !== null) {
            foreach ($servers as $server) {
                if ($server instanceof HetznerServer && !$server->isUpdated() && $server->update($image)) {
                    $server->removeDnsRecords();
                    return true;
                }
            }
        }

        $grow = false;
        $requiresChange = false;
        $toChange = [];

        foreach ($servers as $server) {
            if (!$server instanceof HetznerServer) continue;

            $server->completeDnsRecords();

            if ($server->shouldUpgrade()) {
                if ($server->isBlockingAction()) {
                    return false;
                }
                $requiresChange = true;
                if ($server->canUpgrade()) {
                    $toChange[] = $server;
                }
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $grow |= HetznerComparison::findLeastLevelServer($toChange)->upgrade($servers);
            } else {
                services_self_email(
                    "scaler@system.local",
                    "Hetzner Action: Add Server Required",
                    "Load thresholds exceeded, but no servers can be upgraded in-place. A new server needs to be provisioned manually."
                );
            }
        } elseif (count($servers) > 1) {
            foreach ($servers as $server) {
                if (!$server instanceof HetznerServer) continue;

                if ($server->shouldDowngrade($servers)) {
                    if ($server->isBlockingAction()) {
                        return false;
                    }
                    $requiresChange = true;
                    if ($server->canDowngrade()) {
                        $toChange[] = $server;
                    }
                }
            }

            if ($requiresChange) {
                if (!empty($toChange)) {
                    $grow |= HetznerComparison::findLeastLevelServer($toChange)->downgrade($servers);
                } else {
                    $server = HetznerComparison::findLeastLevelServer($toChange, true);
                    if ($server !== null) {
                        services_self_email(
                            "scaler@system.local",
                            "Hetzner Action: Remove Server Suggested",
                            sprintf("Traffic is low. Consider manually deleting server '%s' (%s) to reduce costs.", $server->name, $server->id)
                        );
                    }
                }
            }
        }

        return (bool)$grow;
    }
}