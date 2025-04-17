<?php

class HetznerAction
{

    private static ?int $defaultImage = null;
    private static ?CloudflareDomain $defaultDomain = null;

    private static function date(string $time = "now"): string
    {
        $dateTime = new DateTime($time);
        return urlencode($dateTime->format(DateTime::ATOM));
    }

    public static function executedAction(array|object|bool|null $query): bool
    {
        if (is_array($query)) {
            return !empty($query)
                && ($query[0]?->action?->error?->code !== null
                    || $query[0]?->action?->error?->message !== null);
        } else {
            return is_object($query)
                && ($query?->action?->error?->code !== null
                    || $query?->action?->error?->message !== null);
        }
    }

    // Separator

    public static function getDefaultImage(): ?string
    {
        if (self::$defaultImage !== null) {
            return self::$defaultImage;
        }
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "images");

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->images as $image) {
                    if ($image->description == HetznerVariables::HETZNER_DEFAULT_IMAGE_NAME) {
                        self::$defaultImage = $image->id;
                        return self::$defaultImage;
                    }
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
        $domain = explode(".", $backup_domain);
        $size = sizeof($domain);

        if ($size >= 2) {
            self::$defaultDomain = new CloudflareDomain(
                $domain[$size - 2] . "." . $domain[$size - 1]
            );
            return self::$defaultDomain;
        } else {
            return null;
        }
    }

    // Separator

    public static function getServers(?array $networks): array
    {
        if (empty($networks)) {
            return array();
        }
        $array = array();
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "servers");

        if (!empty($query)) {
            foreach ($query as $page) {
                $servers = $page?->servers;

                if ($servers == null) {
                    continue;
                }
                foreach ($servers as $server) {
                    $serverID = $server->id;
                    $metrics = get_hetzner_object(
                        HetznerConnectionType::GET,
                        "servers/" . $serverID . "/metrics"
                        . "?type=cpu"
                        . "&start=" . self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds")
                        . "&end=" . self::date()
                        . "&step=" . HetznerVariables::CPU_METRICS_PAST_SECONDS
                    );

                    if (empty($metrics)) {
                        return array();
                    } else {
                        $metrics = $metrics?->metrics?->time_series?->cpu?->values[0][1] ?? null;

                        foreach ($networks as $network) {
                            if ($network->isServerIncluded($serverID)) {
                                $name = $server->name;

                                if (HetznerComparison::shouldConsiderServer($name)) {
                                    $object = new HetznerServer(
                                        $name,
                                        $server->public_net->ipv4->ip,
                                        $serverID,
                                        $metrics === null ? 0.0 : $metrics,
                                        strtolower($server->server_type->architecture) == "x86"
                                            ? new HetznerX86Server(
                                            strtolower($server->server_type->name),
                                            $server->server_type->cores,
                                            $server->server_type->memory,
                                            $server->server_type->disk
                                        ) : new HetznerArmServer(
                                            strtolower($server->server_type->name),
                                            $server->server_type->cores,
                                            $server->server_type->memory,
                                            $server->server_type->disk
                                        ),
                                        new HetznerServerLocation(
                                            $server->datacenter->location->name
                                        ),
                                        $network,
                                        $server->primary_disk_size,
                                        $server->locked,
                                        $server->image->status == "available"
                                        && $server->image->description == HetznerVariables::HETZNER_DEFAULT_IMAGE_NAME
                                    );
                                    $array[$serverID] = $object;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $array;
    }

    public static function getNetworks(): ?array
    {
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "networks");
        $array = array();

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->networks as $network) {
                    $array[$network->id] = new HetznerNetwork(
                        $network->id,
                        $network->servers
                    );
                }
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

        if ($image !== null) {
            $object = new stdClass();

            while (true) {
                $object->name = HetznerVariables::HETZNER_SERVER_NAME_PATTERN . random_number();

                foreach ($servers as $server) {
                    if ($server->name == $object->name) {
                        $object->name = null;
                        break;
                    }
                }

                if ($object->name !== null) {
                    break;
                }
            }

            $object->location = $location->getName();

            $object->image = $image;

            $object->start_after_create = true;

            $object->networks = array(
                $network->getIdentifier()
            );

            if ($serverType instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;
                $object->server_type = $HETZNER_ARM_SERVERS[$level]->getName();
            } else {
                global $HETZNER_X86_SERVERS;
                $object->server_type = $HETZNER_X86_SERVERS[$level]->getName();
            }
            return self::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "servers",
                    json_encode($object)
                )
            );
        }
        return false;
    }

    // Separator

    public static function maintain(array $servers): bool
    {
        $quit = false;

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer)) {
                continue;
            }
            if ($server->isBlockingAction()) { // Wait for all processes to finish
                $server->removeDnsRecords();
                $quit = true;
            }
        }
        if ($quit) {
            return true;
        }
        $size = sizeof($servers);

        if ($size > 1) {
            $image = self::getDefaultImage();

            if ($image !== null) {
                foreach ($servers as $server) {
                    if (!($server instanceof HetznerServer)) {
                        continue;
                    }
                    if (!$server->isUpdated()
                        && $server->update($image)) { // Updates servers one by one
                        $server->removeDnsRecords();
                        return true;
                    }
                }
            }
        }
        $grow = false;

        // Upgrade/Downgrade/Add/Delete Server/s

        $requiresChange = false;
        $toChange = array();

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer)) {
                continue;
            }
            $server->completeDnsRecords();

            if ($server->shouldUpgrade()) {
                if ($server->isBlockingAction()) {
                    return false;
                } else {
                    $requiresChange = true;

                    if ($server->canUpgrade()) {
                        $toChange[] = $server;
                    }
                }
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $grow |= HetznerComparison::findLeastLevelServer($toChange)->upgrade($servers);
            } else {
                foreach ($servers as $server) {
                    if (!($server instanceof HetznerServer)) {
                        continue;
                    }
                    $grow |= HetznerAction::addNewServerBasedOn(
                        $servers,
                        $server->location,
                        $server->network,
                        $server->type,
                        0
                    );
                }
            }
        } else if (sizeof($servers) > 1) {
            foreach ($servers as $server) {
                if (!($server instanceof HetznerServer)) {
                    continue;
                }
                if ($server->shouldDowngrade($servers)) {
                    if ($server->isBlockingAction()) {
                        return false;
                    } else {
                        $requiresChange = true;

                        if ($server->canDowngrade()) {
                            $toChange[] = $server;
                        }
                    }
                }
            }

            if ($requiresChange) {
                if (!empty($toChange)) {
                    $grow |= HetznerComparison::findLeastLevelServer($toChange)->downgrade($servers);
                } else {
                    $server = HetznerComparison::findLeastLevelServer($toChange, true);

                    if ($server !== null) {
                        $grow |= $server->remove();
                    }
                }
            }
        }
        return $grow;
    }

}
