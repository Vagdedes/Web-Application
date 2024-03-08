<?php

class AccountPatreon
{
    private Account $account;
    private ?MethodReply $retrieve;

    public const
        SPARTAN_1_0_JAVA = 1,
        SPARTAN_1_0_BEDROCK = 16,
        SPARTAN_2_0_JAVA = 21,
        SPARTAN_2_0_BEDROCK = 22,
        MOTIVATOR = 4064030,
        SPONSOR = 9784720,
        INVESTOR = 9784718,
        VISIONARY = 21608146,
        SUBSCRIBER = 22435075;

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
                $this->retrieve = $this->find($name, array(self::VISIONARY));

                if ($this->retrieve->isPositiveOutcome()) {
                    $this->account->getPermissions()->addSystemPermission(array(
                        "patreon.subscriber.subscriber"
                    ));
                } else {
                    $this->retrieve = $this->find($name, array(self::INVESTOR));

                    if ($this->retrieve->isPositiveOutcome()) {
                        $this->account->getPermissions()->addSystemPermission(array(
                            "patreon.subscriber.subscriber"
                        ));
                    } else {
                        $this->retrieve = $this->find($name, array(self::SPONSOR));

                        if ($this->retrieve->isPositiveOutcome()) {
                            $this->account->getPermissions()->addSystemPermission(array(
                                "patreon.subscriber.subscriber"
                            ));
                        } else {
                            $this->retrieve = $this->find($name, array(self::MOTIVATOR));

                            if ($this->retrieve->isPositiveOutcome()) {
                                $this->account->getPermissions()->addSystemPermission(array(
                                    "patreon.subscriber.subscriber"
                                ));
                            } else {
                                $this->retrieve = $this->find($name, array(self::SUBSCRIBER));

                                if ($this->retrieve->isPositiveOutcome()) {
                                    $this->account->getPermissions()->addSystemPermission(array(
                                        "patreon.subscriber.subscriber"
                                    ));
                                } else {
                                    $this->retrieve = $this->find($name, null, false);
                                }
                            }
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