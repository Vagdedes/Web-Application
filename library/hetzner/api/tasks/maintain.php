<?php

function hetzner_maintain_network(): bool
{
    $networks = HetznerAction::getNetworks();
    $loadBalancers = HetznerAction::getLoadBalancers($networks);
    $servers = HetznerAction::getServers($networks, $loadBalancers);

    if ($servers !== null) {
        return HetznerAction::growOrShrink($loadBalancers, $servers)
            || HetznerAction::update($servers);
    } else {
        return false;
    }
}
