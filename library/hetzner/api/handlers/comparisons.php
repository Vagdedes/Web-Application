<?php

class HetznerComparison
{

    public static function getX86ServerLevel(HetznerAbstractServer $server, bool $storage = false): int
    {
        global $HETZNER_X86_SERVERS;

        foreach ($HETZNER_X86_SERVERS as $key => $value) {
            if ($server->cpuCores === $value->cpuCores
                && $server->memoryGB === $value->memoryGB
                && $server->pricePerHour === $value->pricePerHour
                && (!$storage || $server->storageGB === $value->storageGB)) {
                return $key;
            }
        }
        return -1;
    }

    public static function getArmServerLevel(HetznerAbstractServer $server, bool $storage = false): int
    {
        global $HETZNER_ARM_SERVERS;

        foreach ($HETZNER_ARM_SERVERS as $key => $value) {
            if ($server->cpuCores === $value->cpuCores
                && $server->memoryGB === $value->memoryGB
                && $server->pricePerHour === $value->pricePerHour
                && (!$storage || $server->storageGB === $value->storageGB)) {
                return $key;
            }
        }
        return -1;
    }

    // Separator

    public static function getLoadBalancerLevel(HetznerLoadBalancer $loadBalancer): int
    {
        global $HETZNER_LOAD_BALANCERS;

        foreach ($HETZNER_LOAD_BALANCERS as $key => $value) {
            if ($loadBalancer->type->targets === $value->targets
                && $loadBalancer->type->maxConnections === $value->maxConnections
                && $loadBalancer->type->pricePerHour === $value->pricePerHour) {
                return $key;
            }
        }
        return -1;
    }

    // Separator

    public static function shouldUpgradeServerLevel(HetznerServer $server): bool
    {
        return $server->cpuPercentage / $server->type->maxCpuPercentage()
            >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

    public static function canUpgradeServer(HetznerServer $server): bool
    {
        if ($server->type instanceof HetznerArmServer) {
            global $HETZNER_ARM_SERVERS;
            $level = self::getArmServerLevel($server->type);
            return $level !== -1 && $level < sizeof($HETZNER_ARM_SERVERS) - 1;
        } else {
            global $HETZNER_X86_SERVERS;
            $level = self::getX86ServerLevel($server->type);
            return $level !== -1 && $level < sizeof($HETZNER_X86_SERVERS) - 1;
        }
    }

    // Separator

    public static function shouldUpgradeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return $loadBalancer->liveConnections / (float)$loadBalancer->type->maxConnections
            >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

}
