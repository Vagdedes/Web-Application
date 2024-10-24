<?php

// BASE

class HetznerVariables
{

    public const
        HETZNER_UPGRADE_USAGE_RATIO = 0.85,
        HETZNER_BACKUP_PRICE_MULTIPLIER = 1.2;

}

class HetznerChanges
{

    public const
        UPGRADE_SERVER = 'upgrade_server',
        DOWNGRADE_SERVER = 'downgrade_server',
        UPGRADE_LOADBALANCER = 'upgrade_loadbalancer',
        DOWNGRADE_LOADBALANCER = 'downgrade_loadbalancer',
        ADD_NEW_SERVER = 'add_new_server',
        REMOVE_SERVER = 'remove_server',
        ADD_NEW_LOADBALANCER = 'add_new_loadbalancer',
        REMOVE_LOADBALANCER = 'remove_loadbalancer';
}

// LOAD BALANCER

$HETZNER_LOAD_BALANCERS = array(
    new HetznerLoadBalancerType(
        'lb11',
        25,
        10_000,
        0.0088
    ),
    new HetznerLoadBalancerType(
        'lb21',
        75,
        20_000,
        0.0253
    ),
    new HetznerLoadBalancerType(
        'lb31',
        150,
        40_000,
        0.0495
    )
);

// LOCATION

$HETZNER_LOCATION_NUREMBERG = new HetznerServerLocation(
    'nbg1',
);

// SNAPSHOT

$HETZNER_APPLICATION_SNAPSHOT = new HetznerServerSnapshot(
    'application-snapshot'
);

// NETWORK

$HETZNER_APPLICATION_NETWORK = new HetznerServerSnapshot(
    'application-network'
);

// ARM SERVER

$HETZNER_ARM_SERVERS = array(
    new HetznerArmServer(
        'cax11',
        2,
        4,
        40,
        0.0053
    ),
    new HetznerArmServer(
        'cax21',
        4,
        8,
        80,
        0.0096
    ),
    new HetznerArmServer(
        'cax31',
        8,
        16,
        160,
        0.0192
    ),
    new HetznerArmServer(
        'cax41',
        16,
        32,
        320,
        0.0384
    )
);

// x86 SERVER

$HETZNER_X86_SERVERS = array(
    new HetznerX86Server(
        'cpx11',
        2,
        2,
        40,
        0.0063
    ),
    new HetznerX86Server(
        'cpx21',
        3,
        4,
        80,
        0.0112
    ),
    new HetznerX86Server(
        'cpx31',
        4,
        8,
        160,
        0.0211
    ),
    new HetznerX86Server(
        'cpx41',
        8,
        16,
        240,
        0.0409
    ),
    new HetznerX86Server(
        'cpx51',
        16,
        32,
        360,
        0.1232
    )
);


