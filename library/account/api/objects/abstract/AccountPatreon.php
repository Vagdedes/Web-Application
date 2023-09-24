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
        INVESTOR = 9784718,
        support_only_tiers = array(self::SUPPORTER),
        products_tiers = array(self::MOTIVATOR, self::SPONSOR, self::INVESTOR);

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->retrieve = null;
    }

    public function retrieve(): MethodReply
    {
        if ($this->retrieve === null) {
            $patreonAccount = $this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1);

            if ($patreonAccount->isPositiveOutcome()) {
                $patreonAccount = $patreonAccount->getObject()[0];
                $this->retrieve = $this->find($patreonAccount, array(self::INVESTOR));

                if ($this->retrieve->isPositiveOutcome()) {
                    $this->account->getPermissions()->addSystemPermission(array(
                        "patreon.subscriber.investor",
                        "patreon.subscriber.products"
                    ));
                } else {
                    $this->retrieve = $this->find($patreonAccount, array(self::SPONSOR));

                    if ($this->retrieve->isPositiveOutcome()) {
                        $this->account->getPermissions()->addSystemPermission(array(
                            "patreon.subscriber.sponsor",
                            "patreon.subscriber.products"
                        ));
                    } else {
                        $this->retrieve = $this->find($patreonAccount, array(self::MOTIVATOR));

                        if ($this->retrieve->isPositiveOutcome()) {
                            $this->account->getPermissions()->addSystemPermission(array(
                                "patreon.subscriber.motivator",
                                "patreon.subscriber.products"
                            ));
                        } else {
                            $this->retrieve = $this->find($patreonAccount, array(self::SUPPORTER));

                            if ($this->retrieve->isPositiveOutcome()) {
                                $this->account->getPermissions()->addSystemPermission(array(
                                    "patreon.subscriber.supporter",
                                    "patreon.subscriber.products"
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

    private function find($patreonAccount, $tiers = null): MethodReply
    {
        $patreonSubscriptions = get_patreon2_subscriptions(null, $tiers);

        if (!empty($patreonSubscriptions)) {
            foreach ($patreonSubscriptions as $subscription) {
                if ($subscription->attributes->full_name == $patreonAccount) {
                    return new MethodReply(true, null, $subscription);
                }
            }
        }
        return new MethodReply(false);
    }
}