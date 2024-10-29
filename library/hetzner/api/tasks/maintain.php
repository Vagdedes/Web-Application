<?php

function hetzner_maintain_network(): bool
{
    $networks = HetznerAction::getNetworks();
    $loadBalancers = HetznerAction::getLoadBalancers($networks);
    $servers = HetznerAction::getServers($networks, $loadBalancers);

    if ($servers !== null) {
        return HetznerAction::grow($loadBalancers, $servers)
            || HetznerAction::update($servers)
            || HetznerAction::optimize($loadBalancers, $servers);
    } else {
        return false;
    }
}
