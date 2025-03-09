<?php
// Dependency
require_once '/var/www/.structure/library/cloudflare/init.php';

// Abstract
require_once '/var/www/.structure/library/hetzner/api/objects/abstract/HetznerAbstractServer.php';

// Placeholders (Object)
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerNetwork.php';
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerServerLocation.php';
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerArmServer.php';
require_once '/var/www/.structure/library/hetzner/api/objects/placeholders/HetznerX86Server.php';

// Executions (Object)
require_once '/var/www/.structure/library/hetzner/api/objects/HetznerServer.php';

// Base
require_once '/var/www/.structure/library/hetzner/api/variables.php';

// Handlers
require_once '/var/www/.structure/library/hetzner/api/handlers/comparisons.php';
require_once '/var/www/.structure/library/hetzner/api/handlers/actions.php';
require_once '/var/www/.structure/library/hetzner/api/handlers/connection.php';

// Tasks
require_once '/var/www/.structure/library/hetzner/api/tasks/maintain.php';
