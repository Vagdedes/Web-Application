<?php

class HetznerLoadBalancer
{

    public string $name, $ipv4;
    public int $identifier;
    public HetznerLoadBalancerType $type;
    public HetznerServerLocation $location;
    public HetznerNetwork $network;
    public int $liveConnections;
    public bool $blockingAction;
    public array $targets;

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
        $this->targets = $targets;
        $this->network = $network;
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

    public function addTarget(HetznerServer $server): bool
    {
        if ($this->hasRemainingTargetSpace()
            && !$this->isTarget($server->identifier)
            && !$server->isInLoadBalancer()) {
            $object = new stdClass();
            $object->type = "server";
            $object->use_private_ip = true;

            $serverObj = new stdClass();
            $serverObj->id = $server->identifier;
            $object->server = $serverObj;

            return HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "load_balancers/" . $this->identifier . "/actions/add_target",
                    json_encode($object)
                )
            );
        }
        return false;
    }

    public function removeTarget(HetznerServer $server): bool
    {
        if ($this->isTarget($server->identifier)
            && $server->isInLoadBalancer()
            && $server->loadBalancer->identifier === $this->identifier) {
            $object = new stdClass();
            $object->type = "server";

            $serverObj = new stdClass();
            $serverObj->id = $server->identifier;
            $object->server = $serverObj;

            return HetznerAction::executedAction(
                get_hetzner_object(
                    HetznerConnectionType::POST,
                    "load_balancers/" . $this->identifier . "/actions/remove_target",
                    json_encode($object)
                )
            );
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

    public function isTarget(string $serverID): int
    {
        return array_key_exists($serverID, $this->targets);
    }

    public function targetCount(): int
    {
        return count($this->targets);
    }

    public function isFull(): bool
    {
        return $this->targetCount() >= $this->type->maxTargets;
    }

    public function isEmpty(): bool
    {
        return $this->targetCount() === 0;
    }

}
