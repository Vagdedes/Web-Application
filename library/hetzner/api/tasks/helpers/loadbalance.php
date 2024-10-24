<?php

function hetzner_load_balance(array $loadBalancers, array $servers): array
{
    $array = array();
    $requiresChange = false;
    $blockingAction = false;
    $toChange = array();

    foreach ($loadBalancers as $arrayKey => $loadBalancer) {
        if (HetznerComparison::shouldConsiderLoadBalancer($loadBalancer)) {
            if (HetznerComparison::shouldUpgradeLoadBalancer($loadBalancer)) {
                if ($loadBalancer->blockingAction) {
                    $requiresChange = false;
                    $toChange = array();
                    $blockingAction = true;
                    break;
                } else {
                    $requiresChange = true;

                    if (HetznerComparison::canUpgradeLoadBalancer($loadBalancer)) {
                        $toChange[] = $loadBalancer;
                    }
                }
            }
        } else {
            unset($loadBalancers[$arrayKey]);
        }
    }

    if ($requiresChange) {
        if (!empty($toChange)) {
            $array[HetznerChanges::UPGRADE_LOADBALANCER] = HetznerComparison::findLeastLevelLoadBalancer($toChange);
        } else {
            $array[HetznerChanges::ADD_NEW_LOADBALANCER] = true;
        }
    } else if (!$blockingAction) {
        foreach ($loadBalancers as $loadBalancer) {
            if (HetznerComparison::shouldDowngradeLoadBalancer($loadBalancer)) {
                if ($loadBalancer->blockingAction) {
                    $requiresChange = false;
                    $toChange = array();
                    break;
                } else {
                    $requiresChange = true;

                    if (HetznerComparison::canDowngradeLoadBalancer($loadBalancer)) {
                        $toChange[] = $loadBalancer;
                    }
                }
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $array[HetznerChanges::DOWNGRADE_LOADBALANCER] = HetznerComparison::findLeastLevelLoadBalancer($toChange);
            } else if (sizeof($loadBalancers) > HetznerVariables::HETZNER_MINIMUM_LOAD_BALANCERS) {
                $loadBalancer = HetznerComparison::findLeastLevelLoadBalancer($toChange, true);

                if ($loadBalancer !== null) {
                    $array[HetznerChanges::REMOVE_LOADBALANCER] = $loadBalancer;
                }
            }
        }
    }

    // Separator

    $requiresChange = false;
    $blockingAction = false;
    $toChange = array();

    foreach ($servers as $arrayKey => $server) {
        if (HetznerComparison::shouldConsiderServer($server)) {
            if (HetznerComparison::shouldUpgradeServer($server)) {
                if ($server->blockingAction) {
                    $requiresChange = false;
                    $toChange = array();
                    $blockingAction = true;
                    break;
                } else {
                    $requiresChange = true;

                    if (HetznerComparison::canUpgradeServer($server)) {
                        $toChange[] = $server;
                    }
                }
            }
        } else {
            unset($servers[$arrayKey]);
        }
    }

    if ($requiresChange) {
        if (!empty($toChange)) {
            $array[HetznerChanges::UPGRADE_SERVER] = HetznerComparison::findLeastLevelServer($toChange);
        } else {
            $array[HetznerChanges::ADD_NEW_SERVER] = true;
        }
    } else if (!$blockingAction) {
        foreach ($servers as $server) {
            if (HetznerComparison::shouldDowngradeServer($server)) {
                if ($server->blockingAction) {
                    $requiresChange = false;
                    $toChange = array();
                    break;
                } else {
                    $requiresChange = true;

                    if (HetznerComparison::canDowngradeServer($server)) {
                        $toChange[] = $server;
                    }
                }
            }
        }

        if ($requiresChange) {
            if (!empty($toChange)) {
                $array[HetznerChanges::DOWNGRADE_SERVER] = HetznerComparison::findLeastLevelServer($toChange);
            } else if (sizeof($servers) > HetznerVariables::HETZNER_MINIMUM_SERVERS) {
                $server = HetznerComparison::findLeastLevelServer($toChange, true);

                if ($server !== null) {
                    $array[HetznerChanges::REMOVE_SERVER] = $server;
                }
            }
        }
    }
    return $array;
}