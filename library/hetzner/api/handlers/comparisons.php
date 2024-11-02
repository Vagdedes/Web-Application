<?php

class HetznerComparison
{

    public static function getServerLevel(HetznerServer $server, bool $storage = false): int
    {
        if ($server->type instanceof HetznerX86Server) {
            global $HETZNER_X86_SERVERS;

            foreach ($HETZNER_X86_SERVERS as $key => $value) {
                if ($server->type->cpuCores === $value->cpuCores
                    && $server->type->memoryGB === $value->memoryGB
                    && $server->type->name === $value->name
                    && (!$storage || $server->type->storageGB === $value->storageGB)) {
                    return $key;
                }
            }
        } else {
            global $HETZNER_ARM_SERVERS;

            foreach ($HETZNER_ARM_SERVERS as $key => $value) {
                if ($server->type->cpuCores === $value->cpuCores
                    && $server->type->memoryGB === $value->memoryGB
                    && $server->type->name === $value->name
                    && (!$storage || $server->type->storageGB === $value->storageGB)) {
                    return $key;
                }
            }
        }
        return -1;
    }

    // Separator

    public static function getLoadBalancerLevel(HetznerLoadBalancerType $loadBalancer): int
    {
        global $HETZNER_LOAD_BALANCERS;

        foreach ($HETZNER_LOAD_BALANCERS as $key => $value) {
            if ($loadBalancer->name === $value->name
                && $loadBalancer->maxTargets === $value->maxTargets
                && $loadBalancer->maxConnections === $value->maxConnections) {
                return $key;
            }
        }
        return -1;
    }

    public static function findIdealLoadBalancerLevel(
        HetznerLoadBalancerType $loadBalancerType,
        int                     $currentTargets,
        int                     $newTargets
    ): int
    {
        global $HETZNER_LOAD_BALANCERS;
        $newTargets += $currentTargets;
        $currentLevel = self::getLoadBalancerLevel($loadBalancerType);
        $lastLevel = sizeof($HETZNER_LOAD_BALANCERS) - 1;

        foreach ($HETZNER_LOAD_BALANCERS as $key => $value) {
            if ($key >= $currentLevel
                && $value->maxTargets >= $newTargets) {
                return $key;
            }
        }
        return $currentLevel === $lastLevel
            ? -1
            : $lastLevel;
    }

    // Separator

    public static function getServerStatus(HetznerServer $server): array
    {
        $array = array();

        foreach (HetznerServerStatus::ALL as $status) {
            if (str_contains($server->name, $status)) {
                $array[] = $status;
            }
        }
        return $array;
    }

    public static function shouldUpgradeServer(HetznerServer $server): bool
    {
        return $server->cpuPercentage / $server->type->maxCpuPercentage()
            >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

    public static function shouldDowngradeServer(HetznerServer $server): bool
    {
        return $server->cpuPercentage / $server->type->maxCpuPercentage()
            <= HetznerVariables::HETZNER_DOWNGRADE_USAGE_RATIO;
    }

    // Separator

    public static function canUpgradeServer(HetznerServer $server): bool
    {
        if ($server->type instanceof HetznerArmServer) {
            global $HETZNER_ARM_SERVERS;
            $level = self::getServerLevel($server);
            return $level !== -1 && $level < sizeof($HETZNER_ARM_SERVERS) - 1;
        } else {
            global $HETZNER_X86_SERVERS;
            $level = self::getServerLevel($server);
            return $level !== -1 && $level < sizeof($HETZNER_X86_SERVERS) - 1;
        }
    }

    public static function canDowngradeServer(HetznerServer $server): bool
    {
        $level = self::getServerLevel($server);

        if ($level > 0) {
            if ($server->type instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;
                return $server->customStorageGB <= $HETZNER_ARM_SERVERS[$level - 1]->storageGB;
            } else {
                global $HETZNER_X86_SERVERS;
                return $server->customStorageGB <= $HETZNER_X86_SERVERS[$level - 1]->storageGB;
            }
        }
        return false;
    }

    // Separator

    public static function canDeleteOrUpdateServer(HetznerServer $server): bool
    {
        return $server->name != HetznerVariables::HETZNER_DEFAULT_SERVER_NAME;
    }

    public static function shouldConsiderServer(HetznerServer $server): bool
    {
        return starts_with($server->name, HetznerVariables::HETZNER_SERVER_NAME_PATTERN);
    }

    // Separator

    public static function shouldUpgradeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return $loadBalancer->liveConnections / (float)$loadBalancer->type->maxConnections
            >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

    public static function shouldDowngradeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return $loadBalancer->liveConnections / (float)$loadBalancer->type->maxConnections
            <= HetznerVariables::HETZNER_DOWNGRADE_USAGE_RATIO;
    }

    // Separator

    public static function canUpgradeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        global $HETZNER_LOAD_BALANCERS;
        $level = self::getLoadBalancerLevel($loadBalancer->type);
        return $level !== -1 && $level < sizeof($HETZNER_LOAD_BALANCERS) - 1;
    }

    public static function canDowngradeLoadBalancer(
        HetznerLoadBalancer $loadBalancer,
        array               $loadBalancers,
        array               $servers
    ): bool
    {
        $level = self::getLoadBalancerLevel($loadBalancer->type);

        if ($level > 0) {
            global $HETZNER_LOAD_BALANCERS;
            $newType = $HETZNER_LOAD_BALANCERS[$level - 1];
            $newFreeSpace = 0;

            foreach ($loadBalancers as $loopLoadBalancer) {
                if ($loopLoadBalancer->identifier === $loadBalancer->identifier) {
                    $newFreeSpace += $newType->maxTargets - $loopLoadBalancer->targetCount();
                } else {
                    $newFreeSpace += $loopLoadBalancer->getRemainingTargetSpace();
                }
            }
            return $newFreeSpace >= sizeof($servers);
        } else {
            return false;
        }
    }

    // Separator

    public static function canDeleteLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return $loadBalancer->name != HetznerVariables::HETZNER_DEFAULT_LOAD_BALANCER_NAME;
    }

    public static function shouldConsiderLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return starts_with($loadBalancer->name, HetznerVariables::HETZNER_LOAD_BALANCER_NAME_PATTERN);
    }

    // Separator

    public static function findLeastLevelServer(array $servers, bool $delete = false): ?HetznerServer
    {
        $min = null;

        foreach ($servers as $server) {
            if ((!$delete || self::canDeleteOrUpdateServer($server))
                && ($min === null || self::getServerLevel($server) < self::getServerLevel($min))) {
                $min = $server;
            }
        }
        return $min;
    }

    public static function findLeastLevelLoadBalancer(array $loadBalancers, bool $delete = false): ?HetznerLoadBalancer
    {
        $min = null;

        foreach ($loadBalancers as $loadBalancer) {
            if ((!$delete || self::canDeleteLoadBalancer($loadBalancer))
                && ($min === null || self::getLoadBalancerLevel($loadBalancer->type) < self::getLoadBalancerLevel($min->type))) {
                $min = $loadBalancer;
            }
        }
        return $min;
    }

    public static function findLeastPopulatedLoadBalancer(array $loadBalancers): ?HetznerLoadBalancer
    {
        $min = null;

        foreach ($loadBalancers as $loadBalancer) {
            if ($min === null || $loadBalancer->targetCount() < $min->targetCount()) {
                $min = $loadBalancer;
            }
        }
        return $min;
    }

}
