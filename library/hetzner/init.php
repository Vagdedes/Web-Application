<?php

// Abstract
require_once '/var/www/.structure/library/hetzner/api/objects/abstract/HetznerAbstractServer.php';

// Placeholders
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerServerLocation.php';
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerLoadBalancerType.php';
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerArmServer.php';
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerX86Server.php';

// Executions
require_once '/var/www/.structure/library/hetzner/api/objects/HetznerLoadBalancer.php';
require_once '/var/www/.structure/library/hetzner/api/objects/HetznerServer.php';

// Base
require_once '/var/www/.structure/library/hetzner/api/variables.php';

// Handlers
require_once '/var/www/.structure/library/hetzner/api/handlers/comparisons.php';
require_once '/var/www/.structure/library/hetzner/api/handlers/actions.php';
require_once '/var/www/.structure/library/hetzner/api/handlers/connection.php';
