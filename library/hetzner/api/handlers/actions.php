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

    public static function getServers(?array $networks, ?array $loadBalancers): ?array
    {
        if (empty($networks)
            || empty($loadBalancers)) {
            return null;
        }
        $array = array();
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "servers");

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->servers as $server) {
                    $loadBalancerOfObject = null;
                    $serverID = $server->id;

                    foreach ($loadBalancers as $loadBalancer) {
                        if ($server->loadBalancer?->identifier === $loadBalancer->identifier) {
                            $loadBalancerOfObject = $loadBalancer;
                        }
                    }
                    $metrics = get_hetzner_object(
                        HetznerConnectionType::GET,
                        "servers/" . $serverID . "/metrics"
                        . "?type=cpu"
                        . "&start=" . self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds")
                        . "&end=" . self::date()
                        . "&step=" . HetznerVariables::CPU_METRICS_PAST_SECONDS
                    );

                    if (empty($metrics)) {
                        return null;
                    } else {
                        $metrics = $metrics?->metrics?->time_series?->cpu?->values[0][1] ?? null;

                        foreach ($networks as $network) {
                            if ($network->isServerIncluded($serverID)) {
                                $name = $server->name;

                                if (HetznerComparison::shouldConsiderServer($name)) {
                                    $object = new HetznerServer(
                                        $name,
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
                                        $loadBalancerOfObject,
                                        new HetznerServerLocation(
                                            $server->datacenter->location->name,
                                            $server->datacenter->location->network_zone
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

    public static function getLoadBalancers(?array $networks): ?array
    {
        if (empty($networks)) {
            return null;
        }
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "load_balancers");
        $array = array();

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->load_balancers as $loadBalancer) {
                    $loadBalancerID = $loadBalancer->id;

                    foreach ($networks as $network) {
                        if ($network->isLoadBalancerIncluded($loadBalancerID)) {
                            $name = $loadBalancer->name;

                            if (HetznerComparison::shouldConsiderLoadBalancer($name)) {
                                $metrics = get_hetzner_object(
                                    HetznerConnectionType::GET,
                                    "load_balancers/" . $loadBalancerID . "/metrics"
                                    . "?type=open_connections"
                                    . "&start=" . self::date("-" . HetznerVariables::CONNECTION_METRICS_PAST_SECONDS . " seconds")
                                    . "&end=" . self::date()
                                    . "&step=" . HetznerVariables::CONNECTION_METRICS_PAST_SECONDS
                                );

                                if (empty($metrics)) {
                                    return null;
                                } else {
                                    $metrics = $metrics?->metrics?->time_series?->open_connections?->values[0][1] ?? null;
                                    $targets = array();

                                    if (!empty($loadBalancer->targets)) {
                                        foreach ($loadBalancer->targets as $target) {
                                            $targets[] = $target?->server?->id;
                                        }
                                    }
                                    $ipv4 = $loadBalancer->public_net->ipv4->ip;
                                    $object = new HetznerLoadBalancer(
                                        $name,
                                        $ipv4,
                                        $loadBalancerID,
                                        $metrics === null ? 0 : $metrics,
                                        new HetznerLoadBalancerType(
                                            strtolower($loadBalancer->load_balancer_type->name),
                                            $loadBalancer->load_balancer_type->max_targets,
                                            $loadBalancer->load_balancer_type->max_connections
                                        ),
                                        new HetznerServerLocation(
                                            $loadBalancer->location->name,
                                            $loadBalancer->location->network_zone
                                        ),
                                        $network,
                                        $targets,
                                    );

                                    $array[$loadBalancerID] = $object;
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
                        $network->servers,
                        $network->load_balancers
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

            $object->location = $location->name;

            $object->image = $image;

            $object->start_after_create = true;

            $object->networks = array(
                $network->identifier
            );

            if ($serverType instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;
                $object->server_type = $HETZNER_ARM_SERVERS[$level]->name;
            } else {
                global $HETZNER_X86_SERVERS;
                $object->server_type = $HETZNER_X86_SERVERS[$level]->name;
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

    public static function addNewLoadBalancerBasedOn(
        array                 $loadBalancers,
        HetznerServerLocation $location,
        HetznerNetwork        $network,
        int                   $level
    ): bool
    {
        global $HETZNER_LOAD_BALANCERS;
        $object = new stdClass();

        while (true) {
            $object->name = HetznerVariables::HETZNER_LOAD_BALANCER_NAME_PATTERN . random_number();

            foreach ($loadBalancers as $loadBalancer) {
                if ($loadBalancer->name == $object->name) {
                    $object->name = null;
                    break;
                }
            }

            if ($object->name !== null) {
                break;
            }
        }

        $algorithm = new stdClass();
        $algorithm->type = "least_connections";
        $object->algorithm = $algorithm;

        $object->load_balancer_type = $HETZNER_LOAD_BALANCERS[$level]->name;

        $object->location = $location->name;

        $object->network = $network->identifier;

        $port = 80;
        $http = new stdClass();
        $http->domain = "";
        $http->path = "/";
        $http->status_codes = array(
            "2??",
            "3??"
        );
        $http->tls = false;

        $healthCheck = new stdClass();
        $healthCheck->proxy_protocol = false;
        $healthCheck->interval = 15;
        $healthCheck->retries = 3;
        $healthCheck->timeout = 10;
        $healthCheck->port = $port;
        $healthCheck->protocol = "http";
        $healthCheck->http = $http;

        $service = new stdClass();
        $service->destination_port = $port;
        $service->listen_port = $port;
        $service->protocol = "http";
        $service->health_check = $healthCheck;
        $service->proxyprotocol = false;

        $object->services = array(
            $service
        );

        return self::executedAction(
            get_hetzner_object(
                HetznerConnectionType::POST,
                "load_balancers",
                json_encode($object)
            )
        );
    }

    // Separator

    public static function maintain(array $loadBalancers, array $servers): bool
    {
        $grow = false;

        // Attach Server/s [And (Optionally) Add Load-Balancer/s]

        $serversToAdd = 0;

        foreach ($servers as $server) {
            if ($server->isBlockingAction()) {
                return false;
            }
            if (!$server->isUpdated()) {
                $grow |= $server->update($servers, HetznerAction::getDefaultImage());
            } else if (!$server->isInLoadBalancer()) {
                $grow |= $server->attachToLoadBalancers($servers, $loadBalancers);
                $serversToAdd++;
            }
        }

        if (!empty($array)) {
            $loadBalancerPositions = 0;

            foreach ($loadBalancers as $loadBalancer) {
                if ($loadBalancer->isBlockingAction()) {
                    return false;
                }
                $loadBalancerPositions += $loadBalancer->getRemainingTargetSpace($servers);
            }
            $serversThatCannotBeAdded = $serversToAdd - $loadBalancerPositions;

            if ($serversThatCannotBeAdded > 0) {
                global $HETZNER_LOAD_BALANCERS;

                while (true) {
                    $loadBalancerToUpgrade = HetznerComparison::findLeastLevelLoadBalancer($loadBalancers);

                    if ($loadBalancerToUpgrade === null) {
                        break;
                    }
                    unset($loadBalancers[$loadBalancerToUpgrade->identifier]);
                    $targetCount = $loadBalancerToUpgrade->targetCount($servers);
                    $newLevel = HetznerComparison::findIdealLoadBalancerLevel(
                        $loadBalancerToUpgrade->type,
                        $targetCount,
                        $serversThatCannotBeAdded
                    );

                    if ($newLevel !== -1
                        && $loadBalancerToUpgrade->upgrade($newLevel)) {
                        $grow = true;
                        $serversThatCannotBeAdded -= $HETZNER_LOAD_BALANCERS[$newLevel]->maxTargets - $targetCount;

                        if ($serversThatCannotBeAdded <= 0) {
                            break;
                        }
                    }
                }

                if ($serversThatCannotBeAdded > 0) {
                    while (true) {
                        $newLevel = HetznerComparison::findIdealLoadBalancerLevel(
                            $HETZNER_LOAD_BALANCERS[0],
                            0,
                            $serversThatCannotBeAdded
                        );

                        if ($newLevel !== -1) {
                            foreach ($loadBalancers as $loopLoadBalancer) {
                                if (HetznerAction::addNewLoadBalancerBasedOn(
                                    $loadBalancers,
                                    $loopLoadBalancer->location,
                                    $loopLoadBalancer->network,
                                    $newLevel
                                )) {
                                    $grow = true;
                                    $serversThatCannotBeAdded -= $HETZNER_LOAD_BALANCERS[$newLevel]->maxTargets;

                                    if ($serversThatCannotBeAdded <= 0) {
                                        break;
                                    }
                                }
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
            return $grow;
        }

        // Finish Server/s Upgrade/Downgrade

        foreach ($servers as $loopServer) {
            $status = $loopServer->getStatus();

            if (!empty($status)) {
                if (in_array(HetznerServerStatus::UPGRADE, $status)) {
                    $grow |= $loopServer->upgrade($servers);
                } else if (in_array(HetznerServerStatus::DOWNGRADE, $status)) {
                    $grow |= $loopServer->downgrade($servers);
                }
            }
        }

        if ($grow) {
            return true;
        }

        // Upgrade/Downgrade/Add/Delete Load-Balancer/s

        $requiresChange = false;
        $toChange = array();

        foreach ($loadBalancers as $loadBalancer) {
            if ($loadBalancer->shouldUpgrade()) {
                if ($loadBalancer->isBlockingAction()) {
                    return false;
                } else {
                    $requiresChange = true;

                    if ($loadBalancer->canUpgrade()) {
                        $toChange[] = $loadBalancer;
                    }
                }
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $grow |= HetznerComparison::findLeastLevelLoadBalancer($toChange)->upgrade();
            } else {
                foreach ($loadBalancers as $loopLoadBalancer) {
                    $grow |= HetznerAction::addNewLoadBalancerBasedOn(
                        $loadBalancers,
                        $loopLoadBalancer->location,
                        $loopLoadBalancer->network,
                        0
                    );
                    break;
                }
            }
        } else {
            foreach ($loadBalancers as $loadBalancer) {
                if ($loadBalancer->shouldDowngrade()) {
                    if ($loadBalancer->isBlockingAction()) {
                        return false;
                    } else {
                        $requiresChange = true;

                        if ($loadBalancer->canDowngrade($loadBalancers, $servers, false)) {
                            $toChange[] = $loadBalancer;
                        }
                    }
                }
            }

            if ($requiresChange) {
                if (!empty($toChange)) {
                    $grow |= HetznerComparison::findLeastLevelLoadBalancer($toChange)->downgrade();
                } else if (sizeof($loadBalancers) > HetznerVariables::HETZNER_MINIMUM_LOAD_BALANCERS) {
                    $loadBalancer = HetznerComparison::findLeastLevelLoadBalancer($toChange, true);

                    if ($loadBalancer !== null) {
                        $targetCount = $loadBalancer->targetCount($servers);
                        $freeSpace = 0;

                        foreach ($loadBalancers as $loopLoadBalancer) {
                            if ($loopLoadBalancer->identifier !== $loadBalancer->identifier) {
                                $freeSpace += $loopLoadBalancer->getRemainingTargetSpace($servers);

                                if ($freeSpace >= $targetCount) {
                                    $grow |= $loadBalancer->remove();
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Upgrade/Downgrade/Add/Delete Server/s

        $requiresChange = false;
        $toChange = array();

        foreach ($servers as $server) {
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
                foreach ($servers as $loopServer) {
                    $grow |= HetznerAction::addNewServerBasedOn(
                        $servers,
                        $loopServer->location,
                        $loopServer->network,
                        $loopServer->type,
                        0
                    );
                }
            }
        } else {
            foreach ($servers as $server) {
                if ($server->shouldDowngrade()) {
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
                } else if (sizeof($servers) > HetznerVariables::HETZNER_MINIMUM_SERVERS) {
                    $server = HetznerComparison::findLeastLevelServer($toChange, true);

                    if ($server !== null) {
                        $loadBalancer = $server->loadBalancer;

                        if ($loadBalancer !== null) {
                            if ($loadBalancer->canDowngrade(
                                $loadBalancers,
                                $servers,
                                true
                            )) {

                            }
                        } else {
                            $grow |= $server->remove();
                        }
                    }
                }
            }
        }
        return $grow;
    }

}
