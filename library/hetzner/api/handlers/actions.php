<?php

class HetznerAction
{

    private static function date(string $time = "now"): string
    {
        $dateTime = new DateTime($time);
        return urlencode($dateTime->format(DateTime::ATOM));
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
                        . "&start=" . self::date("-" . HetznerVariables::CPU_METRICS_PAST_SECONDS . " seconds")
                        . "&end=" . self::date(),
                        false
                    );

                    if (empty($metrics)) {
                        return null;
                    } else {
                        $metrics = $metrics[0]?->metrics?->time_series?->cpu?->values[0][1] ?? null;

                        if ($metrics !== null) {
                            foreach ($networks as $network) {
                                if ($network->isServerIncluded($serverID)) {
                                    $array[$serverID] = new HetznerServer(
                                        $serverID,
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
                                . "&start=" . self::date("-" . HetznerVariables::CONNECTION_METRICS_PAST_SECONDS . " seconds")
                                . "&end=" . self::date(),
                                false
                            );

                            if (empty($metrics)) {
                                return null;
                            } else {
                                $metrics = $metrics[0]?->metrics?->time_series?->connections_per_second?->values;
                                var_dump($metrics);
                                $targets = array();

                                if (!empty($loadBalancer->targets)) {
                                    foreach ($loadBalancer->targets as $target) {
                                        $targets[] = $target?->server?->id;
                                    }
                                }
                                $array[$loadBalancerID] = new HetznerLoadBalancer(
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
        return $array;
    }

    public static function getNetworks(): ?array
    {
        $query = get_hetzner_object_pages("networks");
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

    public static function addNewServerBasedOn(HetznerNetwork $network, int $level): bool
    {
        return false;
    }

    public static function addNewLoadBalancerBasedOn(HetznerNetwork $network, int $level): bool
    {
        return false;
    }

    // Separator

    public static function update(
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

    public static function grow(array $loadBalancers, array $servers): bool
    {
        $grow = false;

        // Attach Server/s [And (Optionally) Add Load-Balancer/s]

        $serversToAdd = 0;

        foreach ($servers as $arrayKey => $server) {
            if (HetznerComparison::shouldConsiderServer($server)) {
                if ($server->blockingAction) {
                    return false;
                }
                if (!$server->isInLoadBalancer()) {
                    $grow |= $server->attachToLoadBalancer($loadBalancers);
                    $serversToAdd++;
                }
            } else {
                unset($servers[$arrayKey]);
            }
        }

        if (!empty($array)) {
            $loadBalancerPositions = 0;

            foreach ($loadBalancers as $loadBalancer) {
                if (HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
                    if ($loadBalancer->blockingAction) {
                        return false;
                    }
                    $loadBalancerPositions += $loadBalancer->getRemainingTargetSpace();
                }
            }
            $serverThatCannotBeAdded = $serversToAdd - $loadBalancerPositions;

            if ($serverThatCannotBeAdded > 0) {
                global $HETZNER_LOAD_BALANCERS;

                while (true) {
                    $loadBalancerToUpgrade = HetznerComparison::findLeastLevelLoadBalancer($loadBalancers);

                    if ($loadBalancerToUpgrade === null) {
                        break;
                    }
                    if (HetznerComparison::canUpgradeLoadBalancer($loadBalancerToUpgrade)) {
                        $newLevel = HetznerComparison::findIdealLoadBalancerLevel(
                            $loadBalancerToUpgrade->type,
                            $loadBalancerToUpgrade->targetCount(),
                            $serverThatCannotBeAdded
                        );

                        if ($newLevel === -1) {
                            break;
                        }
                        unset($loadBalancers[$loadBalancerToUpgrade->identifier]);
                        $serverThatCannotBeAdded -= $HETZNER_LOAD_BALANCERS[$newLevel]->maxTargets - $loadBalancerToUpgrade->targetCount();

                        if ($serverThatCannotBeAdded <= 0) {
                            break;
                        }
                    }
                }

                if ($serverThatCannotBeAdded > 0) {

                }
            }
            return $grow;
        } else {
            foreach ($loadBalancers as $arrayKey => $loadBalancer) {
                if (!HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
                    if ($loadBalancer->blockingAction) {
                        return false;
                    }
                    unset($loadBalancers[$arrayKey]);
                }
            }
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
                $grow |= HetznerAction::addNewLoadBalancerBasedOn(
                    $loadBalancers[0]->network,
                    0
                );
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
                $grow |= HetznerComparison::findLeastLevelServer($toChange)->upgrade();
            } else {
                $grow |= HetznerAction::addNewServerBasedOn(
                    $servers[0]->network,
                    0
                );
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
                    $grow |= HetznerComparison::findLeastLevelServer($toChange)->downgrade();
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
