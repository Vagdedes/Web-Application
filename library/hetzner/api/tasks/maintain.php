<?php
require_once '/var/www/.structure/library/hetzner/api/tasks/helpers/loadbalance.php';

function hetzner_maintain_network(): bool
{
    $array = hetzner_load_balance(
        HetznerAction::getLoadBalancers(),
        HetznerAction::getServers()
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
                    $result &= HetznerAction::addNewServerLike($machine);
                    break;
                case HetznerChanges::ADD_NEW_LOADBALANCER:
                    $result &= HetznerAction::addNewLoadBalancerLike($machine);
                    break;
                default:
                    break;
            }
        }
        return $result;
    } else {
        return HetznerAction::updateServers(
            HetznerAction::getServers()
        );
    }
}
