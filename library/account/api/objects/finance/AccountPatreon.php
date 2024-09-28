<?php

class AccountPatreon
{
    private Account $account;
    private ?MethodReply $retrieve;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->retrieve = null;
    }

    public function retrieve(?array $specificTiers = null, ?int $lifetimeCents = null, bool $and = false): MethodReply
    {
        if ($this->retrieve === null) {
            $name = $this->account->getAccounts()->hasAdded(
                AccountAccounts::PATREON_FULL_NAME,
                null,
                1
            );

            if ($name->isPositiveOutcome()) {
                $this->retrieve = $this->find(
                    $name->getObject()[0],
                    $specificTiers
                );

                if (!$this->retrieve->isPositiveOutcome()) {
                    $this->retrieve = $this->find(
                        $name->getObject()[0],
                        $specificTiers
                    );
                }
            } else {
                $this->retrieve = new MethodReply(false);
            }
        } else {
            $this->retrieve = new MethodReply(false);
        }
        $object = $this->retrieve->getObject();
        return new MethodReply(
            patreon_object_is_paid($object)
            && patreon_object_has_tier($object, null, $specificTiers)
            && (!$and || $lifetimeCents !== null && $object?->attributes?->lifetime_support_cents >= $lifetimeCents),
            $this->retrieve->getMessage(),
            $object
        );
    }

    private function find(string $name, ?array $tiers = null): MethodReply
    {
        $patreonSubscriptions = get_patreon2_subscriptions(null, $tiers, null);

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