<?php

class AccountPatreon
{
    private Account $account;

    public const
        support_only_tiers = array(9804213),
        products_tiers = array(4064030, 9784720, 9784718);

    public function __construct(Account $account)
    {
        $this->account = $account;

        if ($this->get(self::products_tiers)->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission(array(
                "patreon.subscriber.support",
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