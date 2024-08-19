<?php

class AccountPatreon
{
    private Account $account;
    private ?MethodReply $retrieve;

    public const
        SPARTAN_SYN = 7,
        DETECTION_SLOTS_UNLIMITED_PRODUCT = 26;

    private const
        MOTIVATOR_PATREON_TIER = 4064030,
        SPONSOR_PATREON_TIER = 9784720,
        VISIONARY_PATREON_TIER = 21608146;

    public const
        DETECTION_SLOTS_20_TIER = array(
        22435075,
        self::MOTIVATOR_PATREON_TIER,
        self::SPONSOR_PATREON_TIER
    ),
        DETECTION_SLOTS_UNLIMITED_TIER = array(
        23711252, // 6 months split
        23711267, // 5 months split
        23711279, // 4 months split
        23711287, // 3 months split
        23711293, // 2 months split
        23711295, // Pay once
        self::VISIONARY_PATREON_TIER
    );

    public const
        DETECTION_SLOTS_20_PERMISSION = "patreon.spartan.detection.slots.20",
        DETECTION_SLOTS_UNLIMITED_PERMISSION = "patreon.spartan.detection.slots.unlimited";

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->retrieve = null;
    }

    public function retrieve(?array $specificTiers = null): MethodReply
    {
        if ($this->retrieve === null) {
            $name = $this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1);

            if ($name->isPositiveOutcome()) {
                $name = $name->getObject()[0];
                $this->retrieve = $this->find($name, self::DETECTION_SLOTS_20_TIER);

                if ($this->retrieve->isPositiveOutcome()) {
                    $this->account->getPermissions()->addSystemPermission(array(
                        "patreon.subscriber",
                        self::DETECTION_SLOTS_20_PERMISSION
                    ));
                } else {
                    $this->retrieve = $this->find($name, self::DETECTION_SLOTS_UNLIMITED_TIER);

                    if ($this->retrieve->isPositiveOutcome()) {
                        $this->account->getPermissions()->addSystemPermission(array(
                            "patreon.subscriber",
                            self::DETECTION_SLOTS_UNLIMITED_PERMISSION
                        ));
                    } else {
                        $this->retrieve = $this->find($name, null, false);
                    }
                }
            } else {
                $this->retrieve = new MethodReply(false);
            }
        } else {
            $this->retrieve = new MethodReply(false);
        }
        if (empty($specificTiers)) {
            return $this->retrieve;
        } else if (!empty($this->retrieve->getMessage())) {
            $object = $this->retrieve->getObject();
            return new MethodReply(
                patreon_object_has_tier($object, null, $specificTiers),
                $this->retrieve->getMessage(),
                $object
            );
        } else {
            return new MethodReply(
                false,
                $this->retrieve->getMessage(),
                $this->retrieve->getObject()
            );
        }
    }

    private function find(string $name, ?array $tiers = null, bool $paid = true): MethodReply
    {
        $patreonSubscriptions = get_patreon2_subscriptions(null, $tiers, $paid);

        if (!empty($patreonSubscriptions)) {
            $name = trim($name);

            foreach ($patreonSubscriptions as $subscription) {
                if (trim($subscription->attributes->full_name) == $name) {
                    return new MethodReply(true, $paid ? "active" : null, $subscription);
                }
            }
        }
        return new MethodReply(false);
    }
}