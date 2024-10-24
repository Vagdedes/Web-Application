<?php

// BASE

class HetznerVariables
{

    public const
        HETZNER_API_VERSION = 'v1',
        HETZNER_CREDENTIALS_DIRECTORY = "hetzner_credentials",
        HETZNER_UPGRADE_USAGE_RATIO = 0.85,
        HETZNER_DOWNGRADE_USAGE_RATIO = self::HETZNER_UPGRADE_USAGE_RATIO / 2.0,
        HETZNER_MINIMUM_LOAD_BALANCERS = 2,
        HETZNER_MINIMUM_SERVERS = 2,
        HETZNER_DEFAULT_LOAD_BALANCER_NAME = 'balancer-default',
        HETZNER_LOAD_BALANCER_NAME_PATTERN = 'balancer-',
        HETZNER_DEFAULT_SERVER_NAME = 'application-default',
        HETZNER_SERVER_NAME_PATTERN = 'application-',
        HETZNER_DEFAULT_NETWORK = 'application-network',
        HETZNER_DEFAULT_SNAPSHOT = 'application-snapshot';

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

// ARM SERVER

$HETZNER_ARM_SERVERS = array(
    new HetznerArmServer(
        'cax11',
        2,
        4,
        40
    ),
    new HetznerArmServer(
        'cax21',
        4,
        8,
        80
    ),
    new HetznerArmServer(
        'cax31',
        8,
        16,
        160
    ),
    new HetznerArmServer(
        'cax41',
        16,
        32,
        320
    )
);

// x86 SERVER

$HETZNER_X86_SERVERS = array(
    new HetznerX86Server(
        'cpx11',
        2,
        2,
        40
    ),
    new HetznerX86Server(
        'cpx21',
        3,
        4,
        80
    ),
    new HetznerX86Server(
        'cpx31',
        4,
        8,
        160
    ),
    new HetznerX86Server(
        'cpx41',
        8,
        16,
        240
    ),
    new HetznerX86Server(
        'cpx51',
        16,
        32,
        360
    )
);


