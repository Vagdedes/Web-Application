<?php

class HetznerAction
{

    private static function date(string $time = "now"): string
    {
        $dateTime = new DateTime($time);
        return urlencode($dateTime->format(DateTime::ATOM));
    }

    public static function executedAction(array $query): bool
    {
        return !empty($query)
            && ($query[0]?->action?->error?->code !== null
                || $query[0]?->action?->error?->message !== null);
    }

    // Separator

    public static function getDefaultImage(): ?string
    {
        $query = get_hetzner_object_pages(HetznerConnectionType::GET, "images");

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->images as $image) {
                    if ($image->description == HetznerVariables::HETZNER_DEFAULT_IMAGE_NAME) {
                        return $image->id;
                    }
                }
            }
        }
        return null;
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
                        if ($loadBalancer->isTarget($serverID)) {
                            $loadBalancerOfObject = $loadBalancer;
                        }
                    }
                    $metrics = get_hetzner_object_pages(
                        HetznerConnectionType::GET,
                        "servers/" . $serverID . "/metrics"
                        . "?type=cpu"
                        . "&start=" . self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds")
                        . "&end=" . self::date()
                        . "&step=" . HetznerVariables::CPU_METRICS_PAST_SECONDS,
                        null,
                        false
                    );

                    if (empty($metrics)) {
                        return null;
                    } else {
                        $metrics = $metrics[0]?->metrics?->time_series?->cpu?->values[0][1] ?? null;

                        foreach ($networks as $network) {
                            if ($network->isServerIncluded($serverID)) {
                                $object = new HetznerServer(
                                    $server->name,
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

                                if (HetznerComparison::shouldConsiderServer($object)) {
                                    $array[$serverID] = $object;
                                }
                                break;
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
                            $metrics = get_hetzner_object_pages(
                                HetznerConnectionType::GET,
                                "load_balancers/" . $loadBalancerID . "/metrics"
                                . "?type=open_connections"
                                . "&start=" . self::date("-" . HetznerVariables::CONNECTION_METRICS_PAST_SECONDS . " seconds")
                                . "&end=" . self::date()
                                . "&step=" . HetznerVariables::CONNECTION_METRICS_PAST_SECONDS,
                                null,
                                false
                            );

                            if (empty($metrics)) {
                                return null;
                            } else {
                                $metrics = $metrics[0]?->metrics?->time_series?->open_connections?->values[0][1] ?? null;
                                $targets = array();

                                if (!empty($loadBalancer->targets)) {
                                    foreach ($loadBalancer->targets as $target) {
                                        $targets[$target?->server?->id] = true;
                                    }
                                }
                                $object = new HetznerLoadBalancer(
                                    $loadBalancer->name,
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

                                if (HetznerComparison::shouldConsiderLoadBalancer($object)) {
                                    $array[$loadBalancerID] = $object;
                                }
                                break;
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
                get_hetzner_object_pages(
                    HetznerConnectionType::POST,
                    "servers",
                    json_encode($object),
                    false
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
            get_hetzner_object_pages(
                HetznerConnectionType::POST,
                "load_balancers",
                json_encode($object),
                false
            )
        );
    }

    // Separator

    public static function update(array $servers): bool
    {
        $image = self::getDefaultImage();

        if ($image !== null) {
            $update = false;

            foreach ($servers as $server) {
                if (!$server->imageExists) {
                    $update |= $server->update($image);
                }
            }
            return $update;
        }
        return false;
    }

    public static function growOrShrink(array $loadBalancers, array $servers): bool
    {
        if (true) {
            self::getDefaultImage();
            return false;
        }
        $grow = false;

        // Attach Server/s [And (Optionally) Add Load-Balancer/s]

        $serversToAdd = 0;

        foreach ($servers as $server) {
            if ($server->blockingAction) {
                return false;
            }
            if (!$server->isInLoadBalancer()) {
                $grow |= $server->attachToLoadBalancers($servers, $loadBalancers);
                $serversToAdd++;
            }
        }

        if (!empty($array)) {
            $loadBalancerPositions = 0;

            foreach ($loadBalancers as $loadBalancer) {
                if ($loadBalancer->blockingAction) {
                    return false;
                }
                $loadBalancerPositions += $loadBalancer->getRemainingTargetSpace();
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
                    $targetCount = $loadBalancerToUpgrade->targetCount();
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
            } else {
                // todo shrink load balancers
            }
            return $grow;
        }

        // Finish Server/s Upgrade/Downgrade

        foreach ($servers as $loopServer) {
            $status = HetznerComparison::getServerStatus($loopServer);

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
            if (HetznerComparison::shouldUpgradeLoadBalancer($loadBalancer)) {
                if ($loadBalancer->blockingAction) {
                    return false;
                } else {
                    $requiresChange = true;

                    if (HetznerComparison::canUpgradeLoadBalancer($loadBalancer)) {
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
                if (HetznerComparison::shouldDowngradeLoadBalancer($loadBalancer)) {
                    if ($loadBalancer->blockingAction) {
                        return false;
                    } else {
                        $requiresChange = true;

                        if (HetznerComparison::canDowngradeLoadBalancer($loadBalancer, $loadBalancers, $servers)) {
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
                        $grow |= $loadBalancer->remove();
                    }
                }
            }
        }

        // Upgrade/Downgrade/Add/Delete Server/s

        $requiresChange = false;
        $toChange = array();

        foreach ($servers as $server) {
            if (HetznerComparison::shouldUpgradeServer($server)) {
                if ($server->blockingAction) {
                    return false;
                } else {
                    $requiresChange = true;

                    if (HetznerComparison::canUpgradeServer($server)) {
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
                if (HetznerComparison::shouldDowngradeServer($server)) {
                    if ($server->blockingAction) {
                        return false;
                    } else {
                        $requiresChange = true;

                        if (HetznerComparison::canDowngradeServer($server)) {
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
                        $grow |= $server->remove();
                    }
                }
            }
        }
        return $grow;
    }

}
