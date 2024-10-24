<?php

class HetznerAction
{

    public static function getServers(): array
    {
        $array = array();
        $query = get_hetzner_object_pages("servers");

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->servers as $server) {
                    $array[] = new HetznerServer(
                        $server->name,
                        0, // todo
                        $server->server_type->architecture == "x86"
                            ? new HetznerX86Server(
                            strtolower($server->server_type->description),
                            $server->server_type->cores,
                            $server->server_type->memory,
                            $server->server_type->disk
                        ) : new HetznerArmServer(
                            strtolower($server->server_type->description),
                            $server->server_type->cores,
                            $server->server_type->memory,
                            $server->server_type->disk
                        ),
                        null,
                        new HetznerServerLocation($server->datacenter->location->name),
                        false, // todo
                        $server->primary_disk_size,
                        false  // todo
                    );
                    break 2;
                }
            }
        }
        var_dump($array);
        return $array;
    }

    public static function getLoadBalancers(): array
    {
        $array = array();
        return $array;
    }

    // Separator

    public static function addNewServerLike(HetznerServer $server): bool
    {
        return false;
    }

    public static function addNewLoadBalancerLike(HetznerLoadBalancer $loadBalancer): bool
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
