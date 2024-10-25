<?php

function hetzner_maintain_network(): bool
{
    $loadBalancers = HetznerAction::getLoadBalancers();
    $servers = HetznerAction::getServers($loadBalancers);
    $array = HetznerAction::predictGrowthActions(
        $loadBalancers,
        $servers
    );

    if (!empty($array)) {
        $result = false;

        foreach ($array as $action => $machine) {
            switch ($action) {
                case HetznerChanges::UPGRADE_SERVER:
                case HetznerChanges::UPGRADE_LOADBALANCER:
                    $result &= $machine->upgrade();
                    break;
                case HetznerChanges::DOWNGRADE_SERVER:
                case HetznerChanges::DOWNGRADE_LOADBALANCER:
                    $result &= $machine->downgrade();
                    break;
                case HetznerChanges::REMOVE_SERVER:
                case HetznerChanges::REMOVE_LOADBALANCER:
                    $result &= $machine->remove();
                    break;
                case HetznerChanges::ADD_NEW_SERVER:
                    $result &= HetznerAction::addNewServerBasedOn($machine->network);
                    break;
                case HetznerChanges::ADD_NEW_LOADBALANCER:
                    $result &= HetznerAction::addNewLoadBalancerBasedOn($machine->network);
                    break;
                case HetznerChanges::ATTACH_SERVER_TO_LOADBALANCER:
                    $result &= $machine->attachToLoadBalancers($loadBalancers);
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
}
