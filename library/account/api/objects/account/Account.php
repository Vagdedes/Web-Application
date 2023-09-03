<?php

class Account
{
    private $object;
    private AccountSettings $settings;
    private AccountActions $actions;
    private AccountHistory $history;
    private AccountTransactions $transactions;
    private AccountPurchases $purchases;
    private AccountCooldowns $cooldowns;
    private AccountAccounts $accounts;
    private AccountPermissions $permissions;
    private AccountModerations $moderations;
    private AccountDownloads $downloads;
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

    public const IGNORE_APPLICATION = -1;

    public function __construct($applicationID, $id, $email = null, $identification = null, $checkDeletion = true)
    {
        $hasID = $id !== null;
        $hasIdentification = $identification !== null;

        if (!$hasIdentification
            && ($hasID ? (!is_numeric($id) || $id <= 0) : !is_email($email))) {
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
                $this->object = $query[0];
                $this->email = new AccountEmail($this);
                $this->settings = new AccountSettings($this);
                $this->actions = new AccountActions($this);
                $this->history = new AccountHistory($this);
                $this->transactions = new AccountTransactions($this);
                $this->purchases = new AccountPurchases($this);
                $this->cooldowns = new AccountCooldowns($this);
                $this->accounts = new AccountAccounts($this);
                $this->downloads = new AccountDownloads($this);
                $this->permissions = new AccountPermissions($this);
                $this->moderations = new AccountModerations($this);
                $this->password = new AccountPassword($this);
                $this->objectives = new AccountObjectives($this);
                $this->identification = new AccountIdentification($this);
                $this->notifications = new AccountNotifications($this);
                $this->phoneNumber = new AccountPhoneNumber($this);
                $this->reviews = new AccountReviews($this);
                $this->patreon = new AccountPatreon($this);
                $this->correlation = new AccountCorrelation($this);
                $this->verification = new AccountVerification($this);
            } else {
                $this->object = null;
            }
            // Standalone
            $this->team = new AccountTeam($this);
            $this->files = new AccountFiles($this);
            $this->affiliate = new AccountAffiliate($this);
            $this->cooperation = new AccountCooperation($this);
            $this->communication = new AccountCommunication($this);
        }
    }

    public function exists(): bool
    {
        return $this->object !== null;
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
            clear_memory(array(self::class), true);
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

    public function getDownloads(): AccountDownloads
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
}
