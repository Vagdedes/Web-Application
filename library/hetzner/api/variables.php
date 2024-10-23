<?php

// LOAD BALANCER

$HETZNER_LOAD_BALANCER_1 = new HetznerLoadBalancerType(
    'lb11',
    25,
    10_000
);

$HETZNER_LOAD_BALANCER_2 = new HetznerLoadBalancerType(
    'lb21',
    75,
    20_000
);

$HETZNER_LOAD_BALANCER_3 = new HetznerLoadBalancerType(
    'lb31',
    150,
    40_000
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

$HETZNER_ARM_1 = new HetznerArmServer(
    'cax11',
    2,
    4,
    40
);

$HETZNER_ARM_2 = new HetznerArmServer(
    'cax21',
    4,
    8,
    80
);

$HETZNER_ARM_3 = new HetznerArmServer(
    'cax31',
    8,
    16,
    160
);

$HETZNER_ARM_4 = new HetznerArmServer(
    'cax41',
    16,
    32,
    320
);

// x86 SERVER

$HETZNER_X86_1 = new HetznerX86Server(
    'cpx11',
    2,
    2,
    40
);

$HETZNER_X86_2 = new HetznerX86Server(
    'cpx21',
    3,
    4,
    80
);

$HETZNER_X86_3 = new HetznerX86Server(
    'cpx31',
    4,
    8,
    160
);

$HETZNER_X86_4 = new HetznerX86Server(
    'cpx41',
    8,
    16,
    240
);

$HETZNER_X86_4 = new HetznerX86Server(
    'cpx51',
    16,
    32,
    360
);


