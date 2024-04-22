<?php

class AccountPatreon
{
    private Account $account;
    private ?MethodReply $retrieve;

    public const
        SPARTAN_2_0_MOTIVATOR_PATREON_TIER = 4064030,
        SPARTAN_2_0_SPONSOR_PATREON_TIER = 9784720,
        SPARTAN_4_0_INVESTOR_PATREON_TIER = 9784718,
        SPARTAN_4_0_VISIONARY_PATREON_TIER = 21608146,
        SPARTAN_2_0_PATREON_TIER = array(22435075, self::SPARTAN_2_0_MOTIVATOR_PATREON_TIER, self::SPARTAN_2_0_SPONSOR_PATREON_TIER),
        SPARTAN_3_0_PATREON_TIER = array(22808702),
        SPARTAN_4_0_PATREON_TIER = array(22808726, self::SPARTAN_4_0_INVESTOR_PATREON_TIER, self::SPARTAN_4_0_VISIONARY_PATREON_TIER),
        ALL_PATREON_TIERS = array(
        self::SPARTAN_2_0_MOTIVATOR_PATREON_TIER,
        self::SPARTAN_2_0_SPONSOR_PATREON_TIER,
        self::SPARTAN_4_0_INVESTOR_PATREON_TIER,
        self::SPARTAN_4_0_VISIONARY_PATREON_TIER,
        self::SPARTAN_2_0_PATREON_TIER[0],
        self::SPARTAN_3_0_PATREON_TIER[0],
        self::SPARTAN_4_0_PATREON_TIER[0]
    );

    public const
        SPARTAN_4_0_PERMISSION = "patreon.spartan.4.0",
        SPARTAN_3_0_PERMISSION = "patreon.spartan.3.0",
        SPARTAN_2_0_PERMISSION = "patreon.spartan.2.0";

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
                $this->retrieve = $this->find($name, self::SPARTAN_4_0_PATREON_TIER);

                if ($this->retrieve->isPositiveOutcome()) {
                    $this->account->getPermissions()->addSystemPermission(array(
                        "patreon.subscriber",
                        self::SPARTAN_4_0_PERMISSION
                    ));
                } else {
                    $this->retrieve = $this->find($name, self::SPARTAN_3_0_PATREON_TIER);

                    if ($this->retrieve->isPositiveOutcome()) {
                        $this->account->getPermissions()->addSystemPermission(array(
                            "patreon.subscriber",
                            self::SPARTAN_3_0_PERMISSION
                        ));
                    } else {
                        $this->retrieve = $this->find($name, self::SPARTAN_2_0_PATREON_TIER);

                        if ($this->retrieve->isPositiveOutcome()) {
                            $this->account->getPermissions()->addSystemPermission(array(
                                "patreon.subscriber",
                                self::SPARTAN_2_0_PERMISSION
                            ));
                        } else {
                            $this->retrieve = $this->find($name, null, false);
                        }
                    }
                }
            } else {
                $this->retrieve = new MethodReply(false);
            }
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