<?php

class HetznerAction
{

    private static function date($offsetSeconds = 0)
    {
        // Create a new DateTime object initialized with the current time
        $dateTime = new DateTime();

        // Apply the offset in seconds
        if ($offsetSeconds !== 0) {
            $intervalSpec = ($offsetSeconds >= 0 ? '+' : '-') . 'PT' . abs($offsetSeconds) . 'S';
            $dateInterval = new DateInterval($intervalSpec);
            if ($offsetSeconds > 0) {
                $dateTime->add($dateInterval);
            } else {
                $dateTime->sub($dateInterval);
            }
        }

        // Format the date in ISO 8601, including microseconds
        return $dateTime->format('Y-m-d\TH:i:s.u');
    }

    public static function getServers(array $loadBalancers): array
    {
        $array = array();
        $query = get_hetzner_object_pages("servers");

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->servers as $server) {
                    $ipv4 = $server->public_net->ipv4->ip;
                    $ipv6 = $server->public_net->ipv6->ip;
                    $private = array_shift($server->private_net)->ip;
                    $loadBalancerOfObject = null;

                    foreach ($loadBalancers as $loadBalancer) {
                        if ($loadBalancer->hasIP($ipv4)
                            || $loadBalancer->hasIP($ipv6)
                            || $loadBalancer->hasIP($private)) {
                            $loadBalancerOfObject = $loadBalancer;
                        }
                    }
                    var_dump(get_hetzner_object_pages(
                            "servers/" . $server->id . "/metrics"
                            . "?type=cpu"
                            . "&end=" . self::date()
                            . "&start=" . self::date(-300)
                        )
                    );
                    var_dump("servers/" . $server->id . "/metrics"
                        . "?type=cpu"
                        . "&end=" . self::date()
                        . "&start=" . self::date(-300));
                    $array[] = new HetznerServer(
                        $server->name,
                        $server->public_net->ipv4->ip,
                        $server->public_net->ipv6->ip,
                        array_shift($server->private_net)->ip,
                        0, // todo
                        $server->server_type->architecture == "x86"
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
                        null, // todo
                        $server->primary_disk_size,
                        $server->locked
                    );
                    break 2;
                }
            }
        }
        //var_dump($array);
        return $array;
    }

    public static function getLoadBalancers(): array
    {
        $array = array();
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

        // Separator

        foreach ($servers as $arrayKey => $server) {
            if (HetznerComparison::shouldConsiderServer($server)) {
                if (!$server->isInLoadBalancer($loadBalancers)) {
                    $array[HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER] = $server;
                }
            } else {
                unset($servers[$arrayKey]);
            }
        }

        if (!empty($array)) {
            return $array;
        }

        // Separator

        $requiresChange = false;
        $toChange = array();

        foreach ($loadBalancers as $arrayKey => $loadBalancer) {
            if (HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
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
            } else {
                unset($loadBalancers[$arrayKey]);
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $array[HetznerChanges::UPGRADE_LOADBALANCER] = HetznerComparison::findLeastLevelLoadBalancer($toChange);
            } else {
                $array[HetznerChanges::ADD_NEW_LOADBALANCER] = true;
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

        // Separator

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
                $array[HetznerChanges::ADD_NEW_SERVER] = true;
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
