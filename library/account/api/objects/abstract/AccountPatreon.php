<?php

class AccountPatreon
{
    private Account $account;

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

        if ($this->get(array(self::INVESTOR))->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission(array(
                "patreon.subscriber.investor",
                "patreon.subscriber.products"
            ));
        } else if ($this->get(array(self::SPONSOR))->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission(array(
                "patreon.subscriber.sponsor",
                "patreon.subscriber.products"
            ));
        } else if ($this->get(array(self::MOTIVATOR))->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission(array(
                "patreon.subscriber.motivator",
                "patreon.subscriber.products"
            ));
        } else if ($this->get(array(self::SUPPORTER))->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission(array(
                "patreon.subscriber.supporter",
                "patreon.subscriber.products"
            ));
        } else if ($this->get()->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission("patreon.subscriber.support");
        }
    }

    public function get($tiers = null): MethodReply
    {
        $patreonSubscriptions = get_patreon2_subscriptions(null, $tiers);

        if (!empty($patreonSubscriptions)) {
            $patreonAccount = $this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1);

            if ($patreonAccount->isPositiveOutcome()) {
                $patreonAccount = $patreonAccount->getObject()[0];

                foreach ($patreonSubscriptions as $subscription) {
                    if ($subscription->attributes->full_name == $patreonAccount) {
                        return new MethodReply(true, null, $subscription);
                    }
                }
            }
        }
        return new MethodReply(false);
    }
}