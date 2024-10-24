<?php

class HetznerAction
{

    public static function upgradeServer(HetznerServer $server): bool
    {
        return false;
    }

    public static function downgradeServer(HetznerServer $server): bool
    {
        return false;
    }

    // Separator

    public static function addNewServer(HetznerServer $server): bool
    {
        return false;
    }

    public static function removeServer(HetznerServer $server): bool
    {
        return false;
    }

    // Separator

    public static function upgradeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return false;
    }

    public static function downgradeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return false;
    }

    // Separator

    public static function addNewLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return false;
    }

    public static function removeLoadBalancer(HetznerLoadBalancer $loadBalancer): bool
    {
        return false;
    }

    // Separator

    public static function updateServers(array $servers): bool
    {
        // todo check if there was a snapshot in the last 24 hours
        // update all applications but 1
        return false;
    }
}
