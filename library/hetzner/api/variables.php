<?php

// BASE

$UPGRADE_USAGE_RATIO = 0.85;

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


