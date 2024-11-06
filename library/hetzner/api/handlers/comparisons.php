<?php

class HetznerComparison
{

    // Level

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

    // Consider

    public static function shouldConsiderLoadBalancer(HetznerLoadBalancer|string $loadBalancer): bool
    {
        return starts_with(
            is_string($loadBalancer) ? $loadBalancer : $loadBalancer->name,
            HetznerVariables::HETZNER_LOAD_BALANCER_NAME_PATTERN
        );
    }

    public static function shouldConsiderServer(HetznerServer|string $server): bool
    {
        return starts_with(
            is_string($server) ? $server : $server->name,
            HetznerVariables::HETZNER_SERVER_NAME_PATTERN
        );
    }

    // Find

    public static function findLeastLevelServer(array $servers, bool $delete = false): ?HetznerServer
    {
        $min = null;

        foreach ($servers as $server) {
            if ((!$delete || $server->canDeleteOrUpdate())
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
            if ((!$delete || $loadBalancer->canDelete())
                && ($min === null || self::getLoadBalancerLevel($loadBalancer->type) < self::getLoadBalancerLevel($min->type))) {
                $min = $loadBalancer;
            }
        }
        return $min;
    }

    public static function findLeastPopulatedLoadBalancer(array $loadBalancers, array $servers): ?HetznerLoadBalancer
    {
        $object = null;
        $minCpuCores = 0;

        foreach ($loadBalancers as $loadBalancer) {
            if ($object === null) {
                $object = $loadBalancer;
            } else {
                $cpuCores = 0;

                foreach ($servers as $server) {
                    if ($server->loadBalancer?->identifier === $loadBalancer->identifier) {
                        $cpuCores += $server->type->cpuCores;
                    }
                }

                if ($cpuCores < $minCpuCores) {
                    $object = $loadBalancer;
                    $minCpuCores = $cpuCores;
                }
            }
        }
        return $object;
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

    // Redistribute

    public static function canRedistributeLoadBalancerTraffic(array $loadBalancers, HetznerLoadBalancer $toRemove): bool
    {
        $newCount = sizeof($loadBalancers) - 1;

        if ($newCount > 0) {
            $distributedUsageRatio = $toRemove->getUsageRatio() / (float)$newCount;

            foreach ($loadBalancers as $loadBalancer) {
                if ($loadBalancer->identifier !== $toRemove->identifier
                    && $loadBalancer->shouldUpgrade(
                        $loadBalancer->getUsageRatio() + $distributedUsageRatio
                    )) {
                    return false;
                }
            }
        }
        return false;
    }

    public static function canRedistributeServerTraffic(array $servers, HetznerServer $toRemove): bool
    {
        $newCount = sizeof($servers) - 1;

        if ($newCount > 0) {
            $distributedUsageRatioPerCore = $toRemove->getUsageRatio(true) / (float)$newCount;

            foreach ($servers as $server) {
                if ($server->identifier !== $toRemove->identifier
                    && $server->shouldUpgrade(
                        ($server->getUsageRatio(true) + $distributedUsageRatioPerCore) * $server->type->cpuCores
                    )) {
                    return false;
                }
            }
        }
        return false;
    }

}
