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

    public function custom(
        string $name,
        ?array $specificTiers = null,
        ?int   $lifetimeCents = null,
        bool   $and = false
    ): MethodReply
    {
        if (!empty($specificTiers)
            || !empty($lifetimeCents)) {
            $object = $this->find(
                $name,
                $specificTiers
            );
            return new MethodReply(
                ($specificTiers === null
                    || patreon_object_is_paid($object->getObject())
                    && patreon_object_has_tier($object->getObject(), null, $specificTiers))
                && (!$and
                    || $lifetimeCents !== null && $object->getObject()?->attributes?->lifetime_support_cents >= $lifetimeCents),
                $object->getMessage(),
                $object
            );
        } else {
            return new MethodReply(false);
        }
    }

    public function retrieve(
        ?array $specificTiers = null,
        ?int   $lifetimeCents = null,
        bool   $and = false
    ): MethodReply
    {
        if ($this->retrieve === null) {
            if (empty($specificTiers) && empty($lifetimeCents)) {
                $this->retrieve = new MethodReply(false);
            } else {
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
                } else {
                    $this->retrieve = new MethodReply(false);
                }
            }
        }
        $object = $this->retrieve->getObject();
        return new MethodReply(
            ($specificTiers === null
                || patreon_object_is_paid($object)
                && patreon_object_has_tier($object, null, $specificTiers))
            && (!$and
                || $lifetimeCents !== null && $object?->attributes?->lifetime_support_cents >= $lifetimeCents),
            $this->retrieve->getMessage(),
            $object
        );
    }

    private function find(
        string $name,
        ?array $tiers = null,
        string $patreonPageName = "spartananticheat"
    ): MethodReply
    {
        if (strtolower($name) != $patreonPageName) {
            $patreonSubscriptions = get_patreon2_subscriptions(null, $tiers, null);

            if (!empty($patreonSubscriptions)) {
                $name = trim($name);

                foreach ($patreonSubscriptions as $subscription) {
                    if (trim($subscription->attributes->full_name) == $name) {
                        return new MethodReply(true, null, $subscription);
                    }
                }
            }
        }
        return new MethodReply(false);
    }

}