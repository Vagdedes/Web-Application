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

    public function upgrade(array $servers): bool
    {
        $level = HetznerComparison::getServerLevel($this);

        if ($level !== -1) {
            $level += 1;
            $type = null;

            if ($this->type instanceof HetznerArmServer) {
                global $HETZNER_ARM_SERVERS;

                if ($level <= sizeof($HETZNER_ARM_SERVERS)) {
                    $type = $HETZNER_ARM_SERVERS[$level]->name;
                }
            } else {
                global $HETZNER_X86_SERVERS;

                if ($level <= sizeof($HETZNER_X86_SERVERS)) {
                    $type = $HETZNER_X86_SERVERS[$level]->name;
                }
            }

            if ($type !== null) {
                $object = new stdClass();
                $object->server_type = $type;
                $object->upgrade_disk = false;
                $isChanging = !empty(HetznerComparison::getServerStatus($this));

                if (!$isChanging) {
                    $object2 = new stdClass();
                    $object2->name = $this->name . ".up";
                    get_hetzner_object_pages(
                        HetznerConnectionType::PUT,
                        "servers/" . $this->identifier,
                        json_encode($object2),
                        false
                    );
                }
                if ($this->loadBalancer === null
                    || $this->loadBalancer->targetCount() > 1) {
                    get_hetzner_object_pages(
                        HetznerConnectionType::POST,
                        "servers/" . $this->identifier . "/actions/poweroff",
                        null,
                        false
                    );
                    return HetznerAction::executedAction(
                        get_hetzner_object_pages(
                            HetznerConnectionType::POST,
                            "servers/" . $this->identifier . "/actions/change_type",
                            json_encode($object),
                            false
                        )
                    );
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
                $type = $HETZNER_ARM_SERVERS[$level]->name;
            } else {
                global $HETZNER_X86_SERVERS;
                $type = $HETZNER_X86_SERVERS[$level]->name;
            }

            if ($type !== null) {
                $object = new stdClass();
                $object->server_type = $type;
                $object->upgrade_disk = false;
                $isChanging = !empty(HetznerComparison::getServerStatus($this));

                if (!$isChanging) {
                    $object2 = new stdClass();
                    $object2->name = $this->name . ".down";
                    get_hetzner_object_pages(
                        HetznerConnectionType::PUT,
                        "servers/" . $this->identifier,
                        json_encode($object2),
                        false
                    );
                }
                if ($this->loadBalancer === null
                    || $this->loadBalancer->targetCount() > 1) {
                    get_hetzner_object_pages(
                        HetznerConnectionType::POST,
                        "servers/" . $this->identifier . "/actions/poweroff",
                        null,
                        false
                    );
                    return HetznerAction::executedAction(
                        get_hetzner_object_pages(
                            HetznerConnectionType::POST,
                            "servers/" . $this->identifier . "/actions/change_type",
                            json_encode($object),
                            false
                        )
                    );
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

    public function update(int $image): bool
    {
        if (HetznerComparison::canDeleteOrUpdateServer($this)) {
            $object = new stdClass();
            $object->image = $image;
            return HetznerAction::executedAction(
                get_hetzner_object_pages(
                    HetznerConnectionType::POST,
                    "servers/" . $this->identifier . "/actions/rebuild",
                    json_encode($object),
                    false
                )
            );
        }
        return false;
    }

    public function remove(): bool
    {
        return HetznerAction::executedAction(
            get_hetzner_object_pages(
                HetznerConnectionType::DELETE,
                "servers/" . $this->identifier,
                null,
                false
            )
        );
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
                && $server->loadBalancer->hasRemainingTargetSpace()
                && $server->loadBalancer->targetCount() === 1
                && !empty(HetznerComparison::getServerStatus($server))) {
                return $server->loadBalancer->addTarget($this);
            }
        }
        while (true) {
            $loadBalancer = HetznerComparison::findLeastPopulatedLoadBalancer($loadBalancers);

            if ($loadBalancer === null) {
                break;
            }
            if ($loadBalancer->addTarget($this)) {
                return true;
            } else {
                unset($loadBalancers[$loadBalancer->identifier]);
            }
        }
        return false;
    }

}
