<?php

class HetznerServer
{

    public string $name;
    public int $identifier;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public ?HetznerLoadBalancer $loadBalancer;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $customStorageGB;
    public bool $blockingAction, $imageExists;

    public function __construct(string                $name,
                                int                   $identifier,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                ?HetznerLoadBalancer  $loadBalancer,
                                HetznerServerLocation $location,
                                HetznerNetwork        $network,
                                int                   $customStorageGB,
                                bool                  $blockingAction,
                                bool                  $imageExists)
    {
        $this->name = $name;
        $this->identifier = $identifier;
        $this->cpuPercentage = $cpuPercentage;
        $this->network = $network;
        $this->type = $type;
        $this->location = $location;
        $this->loadBalancer = $loadBalancer;
        $this->customStorageGB = $customStorageGB;
        $this->blockingAction = $blockingAction;
        $this->imageExists = $imageExists;
    }

    // Separator

    public function isBlockingAction(): bool
    {
        if ($this->blockingAction) {
            return true;
        }
        $query = get_hetzner_object_pages(
            HetznerConnectionType::GET,
            "servers/" . $this->identifier . "/actions"
        );

        if (!empty($query)) {
            foreach ($query as $page) {
                foreach ($page->actions as $action) {
                    if ($action->finished === null) {
                        $this->blockingAction = true;
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // Separator

    public function upgrade(array $servers): bool
    {
        $level = HetznerComparison::getServerLevel($this);

        if ($level !== -1) {
            $level += 1;
            $type = null;

            if ($this->type instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;

                if ($level <= sizeof($HETZNER_ARM_SERVERS)) {
                    $type = $HETZNER_ARM_SERVERS[$level]?->name;
                }
            } else {
                global $HETZNER_X86_SERVERS;

                if ($level <= sizeof($HETZNER_X86_SERVERS)) {
                    $type = $HETZNER_X86_SERVERS[$level]?->name;
                }
            }

            if ($type !== null) {
                $isChanging = !empty($this->getStatus());

                if (!$isChanging) {
                    $this->rename($this->name . HetznerServerStatus::UPGRADE);
                }
                if ($this->loadBalancer === null
                    || sizeof($this->loadBalancer->allTargets($servers)) > 1) {
                    $this->powerOff();
                    $object = new stdClass();
                    $object->server_type = $type;
                    $object->upgrade_disk = false;

                    if (HetznerAction::executedAction(
                        get_hetzner_object(
                            HetznerConnectionType::POST,
                            "servers/" . $this->identifier . "/actions/change_type",
                            json_encode($object)
                        )
                    )) {
                        $this->blockingAction = true;
                        $this->rename(
                            str_replace(
                                HetznerServerStatus::UPGRADE,
                                "",
                                $this->name
                            )
                        );
                        return true;
                    }
                } else if (!$isChanging) {
                    return HetznerAction::addNewServerBasedOn(
                        $servers,
                        $this->location,
                        $this->network,
                        $this->type,
                        0
                    );
                }
            }
        }
        return false;
    }

    public function downgrade(array $servers): bool
    {
        $level = HetznerComparison::getServerLevel($this);

        if ($level > 0) {
            $level -= 1;

            if ($this->type instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;
                $type = $HETZNER_ARM_SERVERS[$level]?->name;
            } else {
                global $HETZNER_X86_SERVERS;
                $type = $HETZNER_X86_SERVERS[$level]?->name;
            }

            if ($type !== null) {
                $isChanging = !empty($this->getStatus());

                if (!$isChanging) {
                    $this->rename($this->name . HetznerServerStatus::DOWNGRADE);
                }
                if ($this->loadBalancer === null
                    || sizeof($this->loadBalancer->allTargets($servers)) > 1) {
                    $this->powerOff();
                    $object = new stdClass();
                    $object->server_type = $type;
                    $object->upgrade_disk = false;

                    if (HetznerAction::executedAction(
                        get_hetzner_object(
                            HetznerConnectionType::POST,
                            "servers/" . $this->identifier . "/actions/change_type",
                            json_encode($object)
                        )
                    )) {
                        $this->blockingAction = true;
                        $this->rename(
                            str_replace(
                                HetznerServerStatus::DOWNGRADE,
                                "",
                                $this->name
                            )
                        );
                        return true;
                    }
                } else if (!$isChanging) {
                    return HetznerAction::addNewServerBasedOn(
                        $servers,
                        $this->location,
                        $this->network,
                        $this->type,
                        0
                    );
                }
            }
        }
        return false;
    }

    // Separator

    public function update(array $servers, int $image): bool // todo improve
    {
        if ($this->canUpdate()) {
            if ($this->loadBalancer === null) {
                $update = true;
            } else {
                $update = sizeof($this->loadBalancer->allTargets($servers)) === 1
                    || sizeof($this->loadBalancer->activeTargets($servers)) > 1;
            }
            if ($update) {
                $object = new stdClass();
                $object->image = $image;

                if (HetznerAction::executedAction(
                    get_hetzner_object(
                        HetznerConnectionType::POST,
                        "servers/" . $this->identifier . "/actions/rebuild",
                        json_encode($object)
                    )
                )) {
                    $this->blockingAction = true;
                }
            }
        }
        return false;
    }

    public function remove(): bool
    {
        if (HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::DELETE,
                "servers/" . $this->identifier
            )
        )) {
            $this->blockingAction = true;
            $this->loadBalancer = null;
            return true;
        } else {
            return false;
        }
    }

    // Separator

    public function isInLoadBalancer(): bool
    {
        return $this->loadBalancer !== null;
    }

    public function attachToLoadBalancers(array $servers, array $loadBalancers): bool
    {
        foreach ($servers as $server) {
            if ($server->loadBalancer !== null
                && $server->loadBalancer->hasRemainingTargetSpace($servers)
                && sizeof($server->loadBalancer->allTargets($servers)) === 1
                && !empty($this->getStatus())) {
                return $server->loadBalancer->addTarget($servers, $this);
            }
        }
        while (true) {
            $loadBalancer = HetznerComparison::findLeastPopulatedLoadBalancer($loadBalancers, $servers);

            if ($loadBalancer === null) {
                break;
            }
            if ($loadBalancer->addTarget($servers, $this)) {
                return true;
            } else {
                unset($loadBalancers[$loadBalancer->identifier]);
            }
        }
        return false;
    }

    // Separator

    private function getUsageRatio(): float
    {
        return $this->cpuPercentage / $this->type->maxCpuPercentage();
    }

    public function shouldUpgrade(): bool
    {
        return $this->getUsageRatio() >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

    public function shouldDowngrade(array $servers): bool
    {
        return $this->getUsageRatio() <= HetznerVariables::HETZNER_DOWNGRADE_USAGE_RATIO
            && HetznerComparison::canRedistributeServerTraffic(
                $servers,
                $this
            );
    }

    // Separator

    public function canUpgrade(): bool
    {
        if ($this->type instanceof HetznerArmServer) {
            global $HETZNER_ARM_SERVERS;
            $level = HetznerComparison::getServerLevel($this);
            return $level !== -1 && $level < sizeof($HETZNER_ARM_SERVERS) - 1;
        } else {
            global $HETZNER_X86_SERVERS;
            $level = HetznerComparison::getServerLevel($this);
            return $level !== -1 && $level < sizeof($HETZNER_X86_SERVERS) - 1;
        }
    }

    public function canDowngrade(): bool
    {
        $level = HetznerComparison::getServerLevel($this);

        if ($level > 0) {
            if ($this->type instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;
                return $this->customStorageGB <= $HETZNER_ARM_SERVERS[$level - 1]->storageGB;
            } else {
                global $HETZNER_X86_SERVERS;
                return $this->customStorageGB <= $HETZNER_X86_SERVERS[$level - 1]->storageGB;
            }
        }
        return false;
    }

    // Separator

    public function canDelete(): bool
    {
        return $this->name != HetznerVariables::HETZNER_DEFAULT_SERVER_NAME;
    }

    public function canUpdate(): bool
    { // Not possible because default server is where changes originate from
        return $this->name != HetznerVariables::HETZNER_DEFAULT_SERVER_NAME
            && !empty($this->getStatus());
    }

    public function isUpdated(): bool
    {
        return $this->imageExists;
    }

    // Separator

    public function getStatus(): array
    {
        $array = array();

        foreach (HetznerServerStatus::ALL as $status) {
            if (str_contains($this->name, $status)) {
                $array[] = $status;
            }
        }
        return $array;
    }

    // Separator

    private function rename(string $name): bool
    {
        $object2 = new stdClass();
        $object2->name = $name;
        return HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::PUT,
                "servers/" . $this->identifier,
                json_encode($object2)
            )
        );
    }

    private function powerOff(): bool
    {
        return HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::POST,
                "servers/" . $this->identifier . "/actions/poweroff"
            )
        );
    }

}
