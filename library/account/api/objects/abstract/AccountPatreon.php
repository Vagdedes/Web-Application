<?php

class AccountPatreon
{
    private Account $account;
    private ?MethodReply $retrieve;

    public const
        SPARTAN_2_0_JAVA = 21,
        SPARTAN_2_0_BEDROCK = 22,
        SUPPORTER = 9804213,
        MOTIVATOR = 4064030,
        SPONSOR = 9784720,
        INVESTOR = 9784718;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->retrieve = null;
    }

    public function retrieve(): MethodReply
    {
        if ($this->retrieve === null) {
            $name = $this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1);

            if ($name->isPositiveOutcome()) {
                $name = $name->getObject()[0];
                $this->retrieve = $this->find($name, array(self::INVESTOR));

                if ($this->retrieve->isPositiveOutcome()) {
                    $this->account->getPermissions()->addSystemPermission(array(
                        "patreon.subscriber.investor",
                        "patreon.subscriber.products",
                        "patreon.subscriber.ultimatestats",
                        "patreon.subscriber.antialtaccount",
                        "patreon.subscriber.filegui"
                    ));
                } else {
                    $this->retrieve = $this->find($name, array(self::SPONSOR));

                    if ($this->retrieve->isPositiveOutcome()) {
                        $this->account->getPermissions()->addSystemPermission(array(
                            "patreon.subscriber.sponsor",
                            "patreon.subscriber.antialtaccount",
                            "patreon.subscriber.filegui"
                        ));
                    } else {
                        $this->retrieve = $this->find($name, array(self::MOTIVATOR));

                        if ($this->retrieve->isPositiveOutcome()) {
                            $this->account->getPermissions()->addSystemPermission(array(
                                "patreon.subscriber.motivator",
                                "patreon.subscriber.products",
                                "patreon.subscriber.filegui"
                            ));
                        } else {
                            $this->retrieve = $this->find($name, array(self::SUPPORTER));

                            if ($this->retrieve->isPositiveOutcome()) {
                                $this->account->getPermissions()->addSystemPermission(array(
                                    "patreon.subscriber.supporter"
                                ));
                            }
                        }
                    }
                }
            } else {
                $this->retrieve = new MethodReply(false);
            }
        }
        return $this->retrieve;
    }

    private function find(string $name, ?array $tiers = null): MethodReply
    {
        $patreonSubscriptions = get_patreon2_subscriptions(null, $tiers);

        if (!empty($patreonSubscriptions)) {
            $name = trim($name);

            foreach ($patreonSubscriptions as $subscription) {
                if (trim($subscription->attributes->full_name) == $name) {
                    return new MethodReply(true, null, $subscription);
                }
            }
        }
        return new MethodReply(false);
    }
}