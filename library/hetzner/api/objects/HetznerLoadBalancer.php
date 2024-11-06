<?php

class HetznerLoadBalancer
{

    public string $name, $ipv4;
    public int $identifier;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $liveConnections, $targets;
    public bool $blockingAction;

    public function __construct(string                  $name,
                                string                  $ipv4,
                                int                     $identifier,
                                int                     $liveConnections,
                                HetznerLoadBalancerType $type,
                                HetznerServerLocation   $location,
                                HetznerNetwork          $network,
                                array                   $targets)
    {
        $this->name = $name;
        $this->ipv4 = $ipv4;
        $this->identifier = $identifier;
        $this->liveConnections = $liveConnections;
        $this->location = $location;
        $this->type = $type;
        $this->blockingAction = false;
        $this->targets = sizeof($targets);
        $this->network = $network;

        if ($this->targets > 0) {
            $activeTargets = $this->activeTargets($targets);

            if ($activeTargets > 0) {
                HetznerAction::getDefaultDomain()->add_A_DNS(
                    "www",
                    $ipv4,
                    true
                );
            } else {
                HetznerAction::getDefaultDomain()->removeA_DNS("www");
            }
        } else {
            HetznerAction::getDefaultDomain()->removeA_DNS("www");
        }
    }

    // Separator

    public function isBlockingAction(): bool
    {
        if ($this->blockingAction) {
            return true;
        }
        $query = get_hetzner_object_pages(
            HetznerConnectionType::GET,
            "load_balancers/" . $this->identifier . "/actions"
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
        return $this->blockingAction;
    }

    // Separator

    public function upgrade(int $level = 0): bool
    {
        if ($level === 0) {
            $level = HetznerComparison::getLoadBalancerLevel($this->type);

            if ($level === -1) {
                return false;
            }
        }
        global $HETZNER_LOAD_BALANCERS;
        $level += 1;

        if ($level <= sizeof($HETZNER_LOAD_BALANCERS)) {
            $object = new stdClass();
            $object->load_balancer_type = $HETZNER_LOAD_BALANCERS[$level]->name;

            return HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "load_balancers/" . $this->identifier . "/actions/change_type",
                    json_encode($object)
                )
            );
        }
        return false;
    }

    public function downgrade(): bool
    {
        $level = HetznerComparison::getLoadBalancerLevel($this->type);

        if ($level > 0) {
            global $HETZNER_LOAD_BALANCERS;
            $level -= 1;
            $object = new stdClass();
            $object->load_balancer_type = $HETZNER_LOAD_BALANCERS[$level]->name;

            return HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "load_balancers/" . $this->identifier . "/actions/change_type",
                    json_encode($object)
                )
            );
        }
        return false;
    }

    // Separator

    public function remove(): bool
    {
        if (HetznerAction::executedAction(
            get_hetzner_object(
                HetznerConnectionType::DELETE,
                "load_balancers/" . $this->identifier
            )
        )) {
            HetznerAction::getDefaultDomain()->removeA_DNS("www");
            return true;
        } else {
            return false;
        }
    }

    // Separator

    public function addTarget(HetznerServer $server, bool $replace = false): bool
    {
        if ($this->hasRemainingTargetSpace()) {
            $loadBalancer = $server->loadBalancer;

            if ($loadBalancer !== null) {
                if ($loadBalancer->identifier === $this->identifier
                    || !$replace) {
                    return false;
                } else {
                    $loadBalancer->removeTarget($server);
                }
            }
            $object = new stdClass();
            $object->type = "server";
            $object->use_private_ip = true;

            $serverObj = new stdClass();
            $serverObj->id = $server->identifier;
            $object->server = $serverObj;

            if (HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "load_balancers/" . $this->identifier . "/actions/add_target",
                    json_encode($object)
                )
            )) {
                $server->loadBalancer = $this;
                return true;
            }
        }
        return false;
    }

    public function removeTarget(HetznerServer $server): bool
    {
        if ($server->isInLoadBalancer()
            && $server->loadBalancer->identifier === $this->identifier) {
            $object = new stdClass();
            $object->type = "server";

            $serverObj = new stdClass();
            $serverObj->id = $server->identifier;
            $object->server = $serverObj;

            if (HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "load_balancers/" . $this->identifier . "/actions/remove_target",
                    json_encode($object)
                )
            )) {
                $server->loadBalancer = null;
                return true;
            }
        }
        return false;
    }

    // Separator

    public function getRemainingTargetSpace(): int
    {
        return $this->type->maxTargets - $this->targetCount();
    }

    public function hasRemainingTargetSpace(): int
    {
        return $this->getRemainingTargetSpace() > 0;
    }

    // Separator

    public function targetCount(): int
    {
        return $this->targets;
    }

    public function activeTargets(array $servers): int
    {
        $count = 0;

        foreach ($servers as $server) {
            if ($server->loadBalancer?->identifier === $this->identifier
                && !$server->isBlockingAction()) {
                $count++;
            }
        }
        return $count;
    }

    public function isFull(): bool
    {
        return $this->targetCount() >= $this->type->maxTargets;
    }

    public function isEmpty(): bool
    {
        return $this->targetCount() === 0;
    }

    // Separator

    public function getUsageRatio(): float
    {
        return $this->liveConnections / (float)$this->type->maxConnections;
    }

    public function shouldUpgrade(?float $customUsageRatio = null): bool
    {
        return ($customUsageRatio !== null ? $customUsageRatio : $this->getUsageRatio())
            >= HetznerVariables::HETZNER_UPGRADE_USAGE_RATIO;
    }

    public function shouldDowngrade(): bool
    {
        return $this->getUsageRatio() <= HetznerVariables::HETZNER_DOWNGRADE_USAGE_RATIO;
    }

    // Separator

    public function canUpgrade(): bool
    {
        global $HETZNER_LOAD_BALANCERS;
        $level = HetznerComparison::getLoadBalancerLevel($this->type);
        return $level !== -1 && $level < sizeof($HETZNER_LOAD_BALANCERS) - 1;
    }

    public function canDowngrade(
        array $loadBalancers,
        int   $serverCount
    ): bool
    {
        $level = HetznerComparison::getLoadBalancerLevel($this->type);

        if ($level > 0) {
            global $HETZNER_LOAD_BALANCERS;
            $newType = $HETZNER_LOAD_BALANCERS[$level - 1];
            $newFreeSpace = 0;

            foreach ($loadBalancers as $loopLoadBalancer) {
                if ($loopLoadBalancer->identifier === $this->identifier) {
                    $newFreeSpace += $newType->maxTargets - $loopLoadBalancer->targetCount();
                } else {
                    $newFreeSpace += $loopLoadBalancer->getRemainingTargetSpace();
                }
            }
            return $newFreeSpace >= $serverCount;
        } else {
            return false;
        }
    }

    // Separator

    public function canDelete(): bool
    {
        return $this->name != HetznerVariables::HETZNER_DEFAULT_LOAD_BALANCER_NAME;
    }

}
