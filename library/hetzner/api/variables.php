<?php

// BASE

class HetznerVariables
{

    public const
        CPU_METRICS_PAST_SECONDS = 30,
        HETZNER_API_VERSION = "v1",
        HETZNER_CREDENTIALS_DIRECTORY = "hetzner_credentials",
        HETZNER_UPGRADE_USAGE_RATIO = 0.8,
        HETZNER_DOWNGRADE_USAGE_RATIO = 1.0 - self::HETZNER_UPGRADE_USAGE_RATIO,
        HETZNER_DEFAULT_IMAGE_NAME = 'app.snapshot',
        HETZNER_DEFAULT_SERVER_NAME = 'app.default',
        HETZNER_SERVER_NAME_PATTERN = 'app.';

}

class HetznerConnectionType
{
    public const
        GET = "GET",
        POST = "POST",
        PUT = "PUT",
        DELETE = "DELETE";
}

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


