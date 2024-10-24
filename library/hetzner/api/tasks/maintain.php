<?php
require_once '/var/www/.structure/library/hetzner/api/tasks/helpers/loadbalance.php';

function hetzner_maintain_network(): bool
{
    $loadBalancers = array();
    $servers = array();
    $array = hetzner_load_balance($loadBalancers, $servers);

    if (!empty($array)) {
        $result = false;

        foreach ($array as $action => $machine) {
            switch ($action) {
                case HetznerChanges::UPGRADE_SERVER:
                    $result &= HetznerAction::upgradeServer($machine);
                    break;
                case HetznerChanges::DOWNGRADE_SERVER:
                    $result &= HetznerAction::downgradeServer($machine);
                    break;
                case HetznerChanges::ADD_NEW_SERVER:
                    $result &= HetznerAction::addNewServer($machine);
                    break;
                case HetznerChanges::REMOVE_SERVER:
                    $result &= HetznerAction::removeServer($machine);
                    break;
                case HetznerChanges::UPGRADE_LOADBALANCER:
                    $result &= HetznerAction::upgradeLoadBalancer($machine);
                    break;
                case HetznerChanges::DOWNGRADE_LOADBALANCER:
                    $result &= HetznerAction::downgradeLoadBalancer($machine);
                    break;
                case HetznerChanges::ADD_NEW_LOADBALANCER:
                    $result &= HetznerAction::addNewLoadBalancer($machine);
                    break;
                case HetznerChanges::REMOVE_LOADBALANCER:
                    $result &= HetznerAction::removeLoadBalancer($machine);
                    break;
                default:
                    break;
            }
        }
        return $result;
    } else {
        return HetznerAction::updateServers($servers);
    }
}
