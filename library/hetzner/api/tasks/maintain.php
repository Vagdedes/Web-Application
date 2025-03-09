<?php
require_once '/var/www/.structure/library/hetzner/init.php';

function hetzner_maintain_network(): bool
{
    $networks = HetznerAction::getNetworks();
    $servers = HetznerAction::getServers($networks);

    if (!empty($servers)) {
        foreach ($servers as $server) {
            $server->completeDnsRecords();
        }
        return HetznerAction::maintain($servers);
    } else {
        return false;
    }
}
