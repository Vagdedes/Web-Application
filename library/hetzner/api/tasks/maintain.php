<?php
require_once '/var/www/.structure/library/hetzner/init.php';

function hetzner_maintain_network(): bool
{
    $networks = HetznerAction::getNetworks();
    $loadBalancers = HetznerAction::getLoadBalancers($networks);
    $servers = HetznerAction::getServers($networks, $loadBalancers);

    if ($servers !== null) {
        foreach ($loadBalancers as $loadBalancer) {
            $loadBalancer->completeDnsRecords($servers);
        }
        return HetznerAction::maintain($loadBalancers, $servers);
    } else {
        return false;
    }
}
