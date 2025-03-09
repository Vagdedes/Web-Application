<?php

class HetznerServer
{

    public string $name, $ipv4;
    public int $identifier;
    public float $cpuPercentage;
    public HetznerAbstractServer $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $customStorageGB;
    public bool $blockingAction, $imageExists;

    public function __construct(string                $name,
                                string                $ipv4,
                                int                   $identifier,
                                float                 $cpuPercentage,
                                HetznerAbstractServer $type,
                                HetznerServerLocation $location,
                                HetznerNetwork        $network,
                                int                   $customStorageGB,
                                bool                  $blockingAction,
                                bool                  $imageExists)
    {
        $this->name = $name;
        $this->ipv4 = $ipv4;
        $this->identifier = $identifier;
        $this->cpuPercentage = $cpuPercentage;
        $this->network = $network;
        $this->type = $type;
        $this->location = $location;
        $this->customStorageGB = $customStorageGB;
        $this->blockingAction = $blockingAction;
        $this->imageExists = $imageExists;
    }

    // Separator

    public function completeDnsRecords(): bool
    {
        return HetznerAction::getDefaultDomain()->add_A_DNS(
            "www",
            $this->ipv4,
            true
        );
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
                    $type = $HETZNER_ARM_SERVERS[$level]?->getName();
                }
            } else {
                global $HETZNER_X86_SERVERS;

                if ($level <= sizeof($HETZNER_X86_SERVERS)) {
                    $type = $HETZNER_X86_SERVERS[$level]?->getName();
                }
            }

            if ($type !== null) {
                $count = sizeof($servers);

                if ($count > 1) {
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
                        $count--;

                        if ($count == 0) {
                            HetznerAction::addNewServerBasedOn(
                                $servers,
                                $this->location,
                                $this->network,
                                $this->type,
                                0
                            );
                        }
                        $this->blockingAction = true;
                        return true;
                    }
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
                $type = $HETZNER_ARM_SERVERS[$level]?->getName();
            } else {
                global $HETZNER_X86_SERVERS;
                $type = $HETZNER_X86_SERVERS[$level]?->getName();
            }

            if ($type !== null) {
                $count = sizeof($servers);

                if ($count > 1) {
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
                        $count--;

                        if ($count == 0) {
                            HetznerAction::addNewServerBasedOn(
                                $servers,
                                $this->location,
                                $this->network,
                                $this->type,
                                0
                            );
                        }
                        $this->blockingAction = true;
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // Separator

    public function update(int $image): bool
    {
        if ($this->canUpdate()) {
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
                return true;
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
            $this->removeDnsRecords();
            return true;
        } else {
            return false;
        }
    }

    public function removeDnsRecords(): bool
    {
        return HetznerAction::getDefaultDomain()->remove_A_DNS(
            "www",
            $this->ipv4,
        );
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
        if ($this->getUsageRatio() <= HetznerVariables::HETZNER_DOWNGRADE_USAGE_RATIO) {
            foreach ($servers as $server) {
                if (!($server instanceof HetznerServer)) {
                    continue;
                }
                if ($server->identifier !== $this->identifier
                    && $server->shouldUpgrade()) {
                    return false;
                }
            }
            return true;
        }
        return false;
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
                return $this->customStorageGB <= $HETZNER_ARM_SERVERS[$level - 1]->getStorageGB();
            } else {
                global $HETZNER_X86_SERVERS;
                return $this->customStorageGB <= $HETZNER_X86_SERVERS[$level - 1]->getStorageGB();
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
            && !$this->isBlockingAction();
    }

    public function isUpdated(): bool
    {
        return $this->imageExists;
    }

    // Separator

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
