<?php

function hetzner_maintain_network(): bool
{
    $networks = HetznerAction::getNetworks();
    $loadBalancers = HetznerAction::getLoadBalancers($networks);
    $servers = HetznerAction::getServers($networks, $loadBalancers);

    if ($servers !== null) {
        $array = HetznerAction::predictGrowthActions(
            $loadBalancers,
            $servers
        );

        if (!empty($array)) {
            $result = false;

            foreach ($array as $action => $value) {
                switch ($action) {
                    case HetznerChanges::UPGRADE_SERVER:
                        $result &= $value->upgrade();
                        break;
                    case HetznerChanges::UPGRADE_LOADBALANCER:
                        foreach ($value as $loadBalancer) {
                            $result &= $loadBalancer->upgrade();
                        }
                        break;
                    case HetznerChanges::DOWNGRADE_SERVER:
                    case HetznerChanges::DOWNGRADE_LOADBALANCER:
                        $result &= $value->downgrade();
                        break;
                    case HetznerChanges::REMOVE_SERVER:
                    case HetznerChanges::REMOVE_LOADBALANCER:
                        $result &= $value->remove();
                        break;
                    case HetznerChanges::ADD_NEW_SERVER:
                        for ($i = 0; $i < $value; $i++) {
                            $result &= HetznerAction::addNewServerBasedOn($value->network);
                        }
                        break;
                    case HetznerChanges::ADD_NEW_LOADBALANCER:
                        for ($i = 0; $i < $value; $i++) {
                            $result &= HetznerAction::addNewLoadBalancerBasedOn($value->network);
                        }
                        break;
                    case HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER:
                        foreach ($value as $server) {
                            $result &= $server->attachToLoadBalancers($loadBalancers);
                        }
                        break;
                    case HetznerChanges::OPTIMIZE:
                        $result &= HetznerAction::optimize($loadBalancers, $servers);
                        break;
                    default:
                        break;
                }
            }
            return $result;
        } else {
            return HetznerAction::updateServersBasedOnSnapshot($servers);
        }
    } else {
        return false;
    }
}
