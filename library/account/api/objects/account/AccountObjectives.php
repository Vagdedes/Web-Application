<?php

class AccountObjectives
{

    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function get(): array
    {
        if (!$this->account->getEmail()->isVerified()) {
            return $this->create(
                array(),
                "Email Verification",
                "Verify your email by clicking the verification link we have emailed you.",
            );
        } else {
            $array = array();

            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::PAYPAL_EMAIL, null, 1)->isPositiveOutcome()) {
                $array = $this->create(
                    $array,
                    "Purchases & Transactions",
                    "It's best to connect your 'PayPal Email' if you used PayPal to access your purchases.",
                    null,
                    true,
                    "7 days"
                );
            }
            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::SPIGOTMC_URL, null, 1)->isPositiveOutcome()
                && !$this->account->getAccounts()->hasAdded(AccountAccounts::BUILTBYBIT_URL, null, 1)->isPositiveOutcome()
                && !$this->account->getAccounts()->hasAdded(AccountAccounts::POLYMART_URL, null, 1)->isPositiveOutcome()) {
                $array = $this->create( // Do not mention SpigotMC, it's automatically found
                    $array,
                    "Minecraft Platform",
                    "Connect your SpigotMC, BuiltByBit or Polymart account URL to access your owned plugins.",
                    null,
                    true
                );
            }
            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1)->isPositiveOutcome()) {
                $array = $this->create(
                    $array,
                    "Patreon Full Name",
                    "Add your 'Patreon Full Name' if you are a subscriber to access your benefits.",
                    null,
                    true,
                    "7 days"
                );
            } else if ($this->account->getPatreon()->retrieve()->isPositiveOutcome()) {
                if (empty($this->account->getPatreon()->retrieve()->getMessage())) {
                    $array = $this->create(
                        $array,
                        "Free Patreon Tier",
                        "Thanks for subscribing to our free Patreon tier. "
                        . "Feel free to view the paid tiers and their benefits on https://www.idealistic.ai/patreon"
                    );
                }
            }
            return $array;
        }
    }

    private function create(array   $array,
                            string  $title, string $description,
                            ?string $url = null, bool $optionalURL = false,
                            ?string $duration = null): array
    {
        if ($duration === null || get_past_date($duration) <= $this->account->getDetail("creation_date")) {
            $object = new stdClass();
            $object->title = $title;
            $object->description = $description;
            $object->url = $url;
            $object->optional_url = $optionalURL;
            $array[] = $object;
        }
        return $array;
    }

    public function has(): bool
    {
        return !empty($this->get());
    }
}
