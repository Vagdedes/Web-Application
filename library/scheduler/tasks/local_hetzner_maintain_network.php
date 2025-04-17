<?php

function local_hetzner_maintain_network(): bool
{
    require_once '/var/www/.structure/library/hetzner/init.php';
    return hetzner_maintain_network();
}