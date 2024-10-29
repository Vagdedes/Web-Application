<?php

class HetznerAction
{

    private static function date(string $time = "now"): string
    {
        $dateTime = new DateTime($time);
        return $dateTime->format(DateTime::ATOM);
    }

    public static function getServers(?array $networks, ?array $loadBalancers): ?array
    {
        if (empty($networks)
            || empty($loadBalancers)) {
            return null;
        }
        $array = array();
        $query = get_hetzner_object_pages("servers");

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
                        "servers/" . $serverID . "/metrics"
                        . "?type=cpu"
                        . "&start=" . urlencode(self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds"))
                        . "&end=" . urlencode(self::date()),
                        false
                    );

                    if (empty($metrics)) {
                        return null;
                    } else {
                        $metrics = $metrics[0]?->metrics?->time_series?->cpu?->values[0][1] ?? null;

                        if ($metrics !== null) {
                            foreach ($networks as $network) {
                                if ($network->isServerIncluded($serverID)) {
                                    $array[] = new HetznerServer(
                                        $serverID,
                                        $server->public_net->ipv4->ip,
                                        $server->public_net->ipv6->ip,
                                        "", // todo
                                        $metrics,
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
                                            $server->datacenter->location->name
                                        ),
                                        $network,
                                        $server->primary_disk_size,
                                        $server->locked
                                    );
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
        $query = get_hetzner_object_pages("load_balancers");
        $array = array();

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->load_balancers as $loadBalancer) {
                    $loadBalancerID = $loadBalancer->id;

                    foreach ($networks as $network) {
                        if ($network->isLoadBalancerIncluded($loadBalancerID)) {
                            $metrics = get_hetzner_object_pages(
                                "load_balancers/" . $loadBalancerID . "/metrics"
                                . "?type=connections_per_second"
                                . "&start=" . urlencode(self::date("-" . HetznerVariables::CONNECTION_METRICS_PAST_SECONDS . " seconds"))
                                . "&end=" . urlencode(self::date()),
                                false
                            );

                            if (empty($metrics)) {
                                return null;
                            } else {
                                $metrics = $metrics[0]?->metrics?->time_series?->connections_per_second?->values;
                                //var_dump($metrics);
                                $targets = array();

                                if (!empty($loadBalancer->targets)) {
                                    foreach ($loadBalancer->targets as $target) {
                                        $targets[] = $target?->server?->id;
                                    }
                                }
                                $array[] = new HetznerLoadBalancer(
                                    $loadBalancerID,
                                    0, // todo
                                    new HetznerLoadBalancerType(
                                        strtolower($loadBalancer->load_balancer_type->name),
                                        $loadBalancer->load_balancer_type->max_targets,
                                        $loadBalancer->load_balancer_type->max_connections
                                    ),
                                    new HetznerServerLocation(
                                        $loadBalancer->location->name
                                    ),
                                    $network,
                                    $targets,
                                );
                                break 3;
                            }
                        }
                    }
                }
            }
        }
        var_dump($array);
        return $array;
    }

    public static function getNetworks(): ?array
    {
        $query = get_hetzner_object_pages("networks");
        $array = array();

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->networks as $network) {
                    $array[] = new HetznerNetwork(
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

    public static function addNewServerBasedOn(HetznerNetwork $network): bool
    {
        return false;
    }

    public static function addNewLoadBalancerBasedOn(HetznerNetwork $network): bool
    {
        return false;
    }

    // Separator

    public static function updateServersBasedOnSnapshot(
        array  $servers,
        string $snapshot = HetznerVariables::HETZNER_DEFAULT_SNAPSHOT
    ): bool
    {
        return false;
    }

    public static function optimize(array $loadBalancers, array $servers): bool
    {
        return false;
    }

    public static function predictGrowthActions(array $loadBalancers, array $servers): array
    {
        $array = array();

        // Attach/Add Servers And/Or Add Load-Balancers

        foreach ($servers as $arrayKey => $server) {
            if (HetznerComparison::shouldConsiderServer($server)) {
                if (!$server->isInLoadBalancer()) {
                    if (array_key_exists(HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER, $array)) {
                        $array[HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER][] = $server;
                    } else {
                        $array[HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER] = array($server);
                    }
                }
            } else {
                unset($servers[$arrayKey]);
            }
        }

        if (!empty($array)) {
            $serversToAdd = sizeof($array[HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER]);
            $loadBalancerPositions = 0;

            foreach ($loadBalancers as $loadBalancer) {
                if (HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
                    $loadBalancerPositions += $loadBalancer->getRemainingTargetSpace();
                }
            }
            $serverThatCannotAdd = $serversToAdd - $loadBalancerPositions;

            if ($serverThatCannotAdd > 0) {
                $array[HetznerChanges::ADD_NEW_LOADBALANCER] = $serverThatCannotAdd;
            }
            return $array;
        } else {
            foreach ($loadBalancers as $arrayKey => $loadBalancer) {
                if (!HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
                    unset($loadBalancers[$arrayKey]);
                }
            }
        }

        // Upgrade/Downgrade/Add/Delete Load-Balancers

        $requiresChange = false;
        $toChange = array();

        foreach ($loadBalancers as $loadBalancer) {
            if (HetznerComparison::shouldUpgradeLoadBalancer($loadBalancer)) {
                if ($loadBalancer->blockingAction) {
                    return array();
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
                $array[HetznerChanges::UPGRADE_LOADBALANCER] = HetznerComparison::findLeastLevelLoadBalancer($toChange);
            } else {
                $array[HetznerChanges::ADD_NEW_LOADBALANCER] = 1;
            }
        } else {
            foreach ($loadBalancers as $loadBalancer) {
                if (HetznerComparison::shouldDowngradeLoadBalancer($loadBalancer)) {
                    if ($loadBalancer->blockingAction) {
                        return array();
                    } else {
                        $requiresChange = true;

                        if (HetznerComparison::canDowngradeLoadBalancer($loadBalancer)) {
                            $toChange[] = $loadBalancer;
                        }
                    }
                }
            }

            if ($requiresChange) {
                if (!empty($toChange)) {
                    $array[HetznerChanges::DOWNGRADE_LOADBALANCER] = HetznerComparison::findLeastLevelLoadBalancer($toChange);
                } else if (sizeof($loadBalancers) > HetznerVariables::HETZNER_MINIMUM_LOAD_BALANCERS) {
                    $loadBalancer = HetznerComparison::findLeastLevelLoadBalancer($toChange, true);

                    if ($loadBalancer !== null) {
                        $array[HetznerChanges::REMOVE_LOADBALANCER] = $loadBalancer;
                    }
                }
            }
        }

        // Upgrade/Downgrade/Add/Delete Servers

        $requiresChange = false;
        $toChange = array();

        foreach ($servers as $server) {
            if (HetznerComparison::shouldUpgradeServer($server)) {
                if ($server->blockingAction) {
                    return array();
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
                $array[HetznerChanges::UPGRADE_SERVER] = HetznerComparison::findLeastLevelServer($toChange);
            } else {
                $array[HetznerChanges::ADD_NEW_SERVER] = 1;
            }
        } else {
            foreach ($servers as $server) {
                if (HetznerComparison::shouldDowngradeServer($server)) {
                    if ($server->blockingAction) {
                        return array();
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
                    $array[HetznerChanges::DOWNGRADE_SERVER] = HetznerComparison::findLeastLevelServer($toChange);
                } else if (sizeof($servers) > HetznerVariables::HETZNER_MINIMUM_SERVERS) {
                    $server = HetznerComparison::findLeastLevelServer($toChange, true);

                    if ($server !== null) {
                        $array[HetznerChanges::REMOVE_SERVER] = $server;
                    }
                }
            }
        }

        if (empty($array)) {
            $array[HetznerChanges::OPTIMIZE] = true;
        }
        return $array;
    }

}
