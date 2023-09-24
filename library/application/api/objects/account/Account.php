<?php

class Account
{
    private $object;
    private bool $exists;
    private AccountSettings $settings;
    private AccountActions $actions;
    private AccountHistory $history;
    private AccountTransactions $transactions;
    private AccountPurchases $purchases;
    private AccountCooldowns $cooldowns;
    private AccountAccounts $accounts;
    private AccountPermissions $permissions;
    private AccountModerations $moderations;
    private AccountProductDownloads $downloads;
    private AccountPassword $password;
    private AccountEmail $email;
    private AccountObjectives $objectives;
    private AccountIdentification $identification;
    private AccountNotifications $notifications;
    private AccountPhoneNumber $phoneNumber;
    private AccountReviews $reviews;
    private AccountPatreon $patreon;
    private AccountCommunication $communication;
    private AccountTeam $team;
    private AccountFiles $files;
    private AccountCorrelation $correlation;
    private AccountCooperation $cooperation;
    private AccountAffiliate $affiliate;
    private AccountVerification $verification;
    private AccountOffer $offer;
    private AccountProduct $product;
    private AccountGiveaway $giveaway;
    private AccountFunctionality $functionality;
    private AccountWallet $wallet;
    private AccountStatistics $statistics;
    private AccountReference $reference;

    public const IGNORE_APPLICATION = -1;

    public function __construct($applicationID, $id, $email = null, $identification = null, $checkDeletion = true)
    {
        $hasID = $id !== null;
        $hasIdentification = $identification !== null;

        if (!$hasIdentification
            && ($hasID ? (!is_numeric($id) || $id <= 0) : !is_email($email))) {
            $this->exists = false;
            $this->object = null;
        } else {
            if ($hasIdentification) {
                global $account_identification_table;
                set_sql_cache(null, self::class);
                $query = get_sql_query(
                    $account_identification_table,
                    array("account_id"),
                    array(
                        array("code", $identification)
                    ),
                    null,
                    1
                );

                if (empty($query)) {
                    $this->object = null;
                    return;
                } else {
                    $id = $query[0]->account_id;
                    $hasID = true;
                }
            }
            global $accounts_table;
            set_sql_cache(null, self::class);
            $query = get_sql_query(
                $accounts_table,
                null,
                array(
                    $hasID ? array("id", $id) : "",
                    $email !== null ? array("email_address", $email) : "",
                    $checkDeletion ? array("deletion_date", null) : "",
                    $applicationID !== self::IGNORE_APPLICATION ? array("application_id", $applicationID) : ""
                ),
                null,
                1
            );

            if (!empty($query)) {
                $this->exists = true;
                $this->object = $query[0];
                $this->email = new AccountEmail($this);
                $this->settings = new AccountSettings($this);
                $this->history = new AccountHistory($this);
                $this->transactions = new AccountTransactions($this);
                $this->purchases = new AccountPurchases($this);
                $this->cooldowns = new AccountCooldowns($this);
                $this->accounts = new AccountAccounts($this);
                $this->permissions = new AccountPermissions($this);
                $this->objectives = new AccountObjectives($this);
                $this->identification = new AccountIdentification($this);
                $this->notifications = new AccountNotifications($this);
                $this->phoneNumber = new AccountPhoneNumber($this);
                $this->reviews = new AccountReviews($this);
                $this->patreon = new AccountPatreon($this);
                $this->correlation = new AccountCorrelation($this);
                $this->verification = new AccountVerification($this);
                $this->statistics = new AccountStatistics($this);
            } else {
                $this->exists = false;
                $this->object = new stdClass();
                $this->object->application_id = $applicationID;
            }
        }
        // Standalone
        $this->actions = new AccountActions($this);
        $this->password = new AccountPassword($this);
        $this->downloads = new AccountProductDownloads($this);
        $this->team = new AccountTeam($this);
        $this->files = new AccountFiles($this);
        $this->affiliate = new AccountAffiliate($this);
        $this->cooperation = new AccountCooperation($this);
        $this->communication = new AccountCommunication($this);
        $this->offer = new AccountOffer($this);
        $this->product = new AccountProduct($this);
        $this->giveaway = new AccountGiveaway($this);
        $this->moderations = new AccountModerations($this);
        $this->functionality = new AccountFunctionality($this);
        $this->wallet = new AccountWallet($this);
        $this->reference = new AccountReference($this);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function getDetail($detail)
    {
        return $this->object->{$detail} ?? null;
    }

    public function setDetail($detail, $value): MethodReply
    {
        if ($this->object->{$detail} !== $value) {
            global $accounts_table;
            if (!set_sql_query(
                $accounts_table,
                array($detail => $value),
                array(
                    array("id", $this->object->id)
                ),
                null,
                1
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            $this->clearMemory(self::class);
            $this->object->{$detail} = $value;
        }
        return new MethodReply(true);
    }

    public function getObject(): ?object
    {
        return $this->object;
    }

    public function getSettings(): AccountSettings
    {
        return $this->settings;
    }

    public function getActions(): AccountActions
    {
        return $this->actions;
    }

    public function getHistory(): AccountHistory
    {
        return $this->history;
    }

    public function getTransactions(): AccountTransactions
    {
        return $this->transactions;
    }

    public function getPurchases(): AccountPurchases
    {
        return $this->purchases;
    }

    public function getCooldowns(): AccountCooldowns
    {
        return $this->cooldowns;
    }

    public function getAccounts(): AccountAccounts
    {
        return $this->accounts;
    }

    public function getDownloads(): AccountProductDownloads
    {
        return $this->downloads;
    }

    public function getPermissions(): AccountPermissions
    {
        return $this->permissions;
    }

    public function getModerations(): AccountModerations
    {
        return $this->moderations;
    }

    public function getPassword(): AccountPassword
    {
        return $this->password;
    }

    public function getEmail(): AccountEmail
    {
        return $this->email;
    }

    public function getObjectives(): AccountObjectives
    {
        return $this->objectives;
    }

    public function getIdentification(): AccountIdentification
    {
        return $this->identification;
    }

    public function getNotifications(): AccountNotifications
    {
        return $this->notifications;
    }

    public function getPhoneNumber(): AccountPhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getReviews(): AccountReviews
    {
        return $this->reviews;
    }

    public function getPatreon(): AccountPatreon
    {
        return $this->patreon;
    }

    public function getCommunication(): AccountCommunication
    {
        return $this->communication;
    }

    public function getTeam(): AccountTeam
    {
        return $this->team;
    }

    public function getFiles(): AccountFiles
    {
        return $this->files;
    }

    public function getCorrelation(): AccountCorrelation
    {
        return $this->correlation;
    }

    public function getCooperation(): AccountCooperation
    {
        return $this->cooperation;
    }

    public function getAffiliate(): AccountAffiliate
    {
        return $this->affiliate;
    }

    public function getVerification(): AccountVerification
    {
        return $this->verification;
    }

    public function getOffer(): AccountOffer
    {
        return $this->offer;
    }

    public function getProduct(): AccountProduct
    {
        return $this->product;
    }

    public function getGiveaway(): AccountGiveaway
    {
        return $this->giveaway;
    }

    public function getFunctionality(): AccountFunctionality
    {
        return $this->functionality;
    }

    public function getWallet(): AccountWallet
    {
        return $this->wallet;
    }

    public function getStatistics(): AccountStatistics
    {
        return $this->statistics;
    }

    public function getReference(): AccountReference
    {
        return $this->reference;
    }

    // Separator

    public function refresh()
    {
        if ($this->exists
            && isset($this->transactions)) {
            $this->getTransactions()->clearCache();
            $this->getTransactions()->getSuccessful();
        }
    }

    public function clearMemory($key = null)
    {
        if (isset($this->object->id)) {
            if ($key === null) {
                clear_memory(array(get_sql_cache_key("account_id", $this->object->id)), true);
            } else if (is_array($key)) {
                $key1 = get_sql_cache_key("account_id", $this->object->id);

                foreach ($key as $item) {
                    clear_memory(array(array($item, $key1)), true);
                }
            } else {
                clear_memory(
                    array(array(
                        $key,
                        get_sql_cache_key("account_id", $this->object->id)
                    )), true
                );
            }
        } else if ($key !== null) {
            clear_memory(array($key), true);
        }
    }
}
