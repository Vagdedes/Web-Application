<?php

// BASE

class HetznerVariables
{

    public const
        CPU_METRICS_PAST_SECONDS = 30,
        CONNECTION_METRICS_PAST_SECONDS = 30,
        HETZNER_API_VERSION = "v1",
        HETZNER_CREDENTIALS_DIRECTORY = "hetzner_credentials",
        HETZNER_UPGRADE_USAGE_RATIO = 0.85,
        HETZNER_DOWNGRADE_USAGE_RATIO = self::HETZNER_UPGRADE_USAGE_RATIO / 2.0,
        HETZNER_MINIMUM_LOAD_BALANCERS = 1,
        HETZNER_MINIMUM_SERVERS = 2, // 2 because of upgrades/downgrades that still require 1 for the load balancers
        HETZNER_DEFAULT_LOAD_BALANCER_NAME = 'app.lb.default',
        HETZNER_DEFAULT_IMAGE_NAME = 'app.snapshot',
        HETZNER_LOAD_BALANCER_NAME_PATTERN = 'app.lb.',
        HETZNER_DEFAULT_SERVER_NAME = 'app.default',
        HETZNER_SERVER_NAME_PATTERN = 'app.';

}

class HetznerServerStatus
{
    public const
        UPGRADE = ".a",
        DOWNGRADE = ".b",
        ALL = array(
        self::UPGRADE,
        self::DOWNGRADE
    );
}

class HetznerConnectionType
{
    public const
        GET = "GET",
        POST = "POST",
        PUT = "PUT",
        DELETE = "DELETE";
}

// LOAD BALANCER

$HETZNER_LOAD_BALANCERS = array(
    new HetznerLoadBalancerType(
        'lb11',
        25,
        10_000
    ),
    new HetznerLoadBalancerType(
        'lb21',
        75,
        20_000
    ),
    new HetznerLoadBalancerType(
        'lb31',
        150,
        40_000
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


