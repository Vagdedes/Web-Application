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
            return array(
                $this->create(
                    "Email Verification",
                    "Verify your email by clicking the verification link we have emailed you."
                )
            );
        } else {
            global $website_url;

            $array = array();
            $paypal = $this->account->getAccounts()->hasAdded(AccountAccounts::PAYPAL_EMAIL, null, 1)->isPositiveOutcome();

            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::STRIPE_EMAIL, null, 1)->isPositiveOutcome()) {
                $array[] = $this->create(
                    "Purchases & Transactions",
                    "Add your" . (!$paypal ? " PayPal and " : " ") . "Stripe email to have your purchases identified.",
                    $website_url . "/profile/addAccount"
                );
            } else if (!$paypal) {
                $array[] = $this->create(
                    "Purchases & Transactions",
                    "Add your PayPal email to have your purchases identified.",
                    $website_url . "/profile/addAccount"
                );
            }
            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::SPIGOTMC_URL, null, 1)->isPositiveOutcome()
                && !$this->account->getAccounts()->hasAdded(AccountAccounts::BUILTBYBIT_URL, null, 1)->isPositiveOutcome()
                && !$this->account->getAccounts()->hasAdded(AccountAccounts::POLYMART_URL, null, 1)->isPositiveOutcome()) {
                $array[] = $this->create( // Do not mention SpigotMC, it's automatically found
                    "Minecraft Platform",
                    "Add your BuiltByBit/Polymart account URL to have your licenses identified.",
                    $website_url . "/profile/addAccount"
                );
            }
            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::DISCORD_TAG, null, 1)->isPositiveOutcome()) {
                $array[] = $this->create(
                    "Discord Tag",
                    "Add your Discord-Tag so we can give you roles on Discord now or in the future.",
                    $website_url . "/profile/addAccount"
                );
            }
            if (!$this->account->getAccounts()->hasAdded(AccountAccounts::PATREON_FULL_NAME, null, 1)->isPositiveOutcome()) {
                $array[] = $this->create(
                    "Patreon Full Name",
                    "Add your Patreon-Full-Name to have your purchases identified.",
                    $website_url . "/profile/addAccount"
                );
            }
            return $array;
        }
    }

    private function create($title, $description, $url = null): object
    {
        $object = new stdClass();
        $object->title = $title;
        $object->description = $description;
        $object->url = $url;
        return $object;
    }

    public function has(): bool
    {
        return !empty($this->get());
    }
}
