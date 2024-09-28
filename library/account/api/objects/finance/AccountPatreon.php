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

    public function retrieve(?array $specificTiers = null): MethodReply
    {
        if ($this->retrieve === null) {
            $name = $this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1);

            if ($name->isPositiveOutcome()) {
                $name = $name->getObject()[0];
                $this->retrieve = $this->find($name, GameCloudVariables::DETECTION_SLOTS_UNLIMITED_TIER);

                if ($this->retrieve->isPositiveOutcome()) {
                    $this->account->getPurchases()->add(
                        GameCloudVariables::DETECTION_SLOTS_UNLIMITED_PRODUCT,
                        null,
                        null,
                        null,
                        null,
                        "1 day"
                    );
                } else {
                    $this->retrieve = $this->find($name, null, false);

                    if ($this->retrieve->isPositiveOutcome()) {
                        $object = $this->retrieve->getObject();

                        if ($object->attributes->lifetime_support_cents
                            >= GameCloudVariables::DETECTION_SLOTS_UNLIMITED_REQUIRED_EUR * 1000) {
                            $this->account->getPurchases()->add(
                                GameCloudVariables::DETECTION_SLOTS_UNLIMITED_PRODUCT
                            );
                        }
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