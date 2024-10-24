<?php

function hetzner_load_balance(): void
{

}

function hetzner_load_balance_steps(array $loadBalancers, array $servers): array
{
    $array = array();
    $changeRunning = false; // todo

    if (!$changeRunning) {
        $requiresChange = false;
        $toChange = array();

        foreach ($loadBalancers as $arrayKey => $loadBalancer) {
            if (HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
                if (HetznerComparison::shouldUpgradeLoadBalancer($loadBalancer)) {
                    $requiresChange = true;

                    if (HetznerComparison::canUpgradeLoadBalancer($loadBalancer)) {
                        $toChange[] = $loadBalancer;
                        break;
                    }
                }
            } else {
                unset($loadBalancers[$arrayKey]);
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $array[HetznerChanges::UPGRADE_LOADBALANCER] = $toChange; // todo find minimum upgraded
            } else {
                $array[HetznerChanges::ADD_NEW_LOADBALANCER] = true;
            }
        } else {
            foreach ($loadBalancers as $loadBalancer) {
                if (HetznerComparison::shouldDowngradeLoadBalancer($loadBalancer)) {
                    $requiresChange = true;

                    if (HetznerComparison::canDowngradeLoadBalancer($loadBalancer)) {
                        $toChange[] = $loadBalancer;
                        break;
                    }
                }
            }

            if ($requiresChange) {
                if (!empty($toChange)) {
                    $array[HetznerChanges::DOWNGRADE_LOADBALANCER] = $toChange;
                } else if (sizeof($loadBalancers) > HetznerVariables::HETZNER_MINIMUM_LOAD_BALANCERS) {
                    $array[HetznerChanges::REMOVE_LOADBALANCER] = true; // todo find minimum upgraded
                }
            }
        }
    }

    // Separator

    $changeRunning = false; // todo

    if (!$changeRunning) {
        $requiresChange = false;
        $toChange = array();

        foreach ($servers as $arrayKey => $server) {
            if (HetznerComparison::shouldConsiderServer($server)) {
                if (HetznerComparison::shouldUpgradeServer($server)) {
                    $requiresChange = true;

                    if (HetznerComparison::canUpgradeServer($server)) {
                        $toChange[] = $server;
                        break;
                    }
                }
            } else {
                unset($servers[$arrayKey]);
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $array[HetznerChanges::UPGRADE_SERVER] = $toChange; // todo find minimum upgraded
            } else {
                $array[HetznerChanges::ADD_NEW_SERVER] = true;
            }
        } else {
            foreach ($servers as $server) {
                if (HetznerComparison::shouldDowngradeServer($server)) {
                    $requiresChange = true;

                    if (HetznerComparison::canDowngradeServer($server)) {
                        $toChange = $server;
                        break;
                    }
                }
            }

            if ($requiresChange) {
                if (!empty($toChange)) {
                    $array[HetznerChanges::DOWNGRADE_SERVER] = $toChange;
                } else if (sizeof($servers) > HetznerVariables::HETZNER_MINIMUM_SERVERS) {
                    $array[HetznerChanges::REMOVE_SERVER] = true; // todo find minimum upgraded
                }
            }
        }
    }
    return $array;
}