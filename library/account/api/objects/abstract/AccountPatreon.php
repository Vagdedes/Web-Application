<?php

class AccountPatreon
{
    private Account $account;

    private const patreon_low_tiers = array(9790579, 9804213);

    public function __construct(Account $account)
    {
        $this->account = $account;

        if ($this->get()->isPositiveOutcome()) {
            $account->getPermissions()->addSystemPermission("patreon.subscriber");
        }
    }

    public function get($all = false): MethodReply
    {
        if ($all) {
            $patreonSubscriptions = get_patreon_subscriptions();
        } else {
            $patreonSubscriptions = get_patreon_subscriptions(self::patreon_low_tiers);
        }

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