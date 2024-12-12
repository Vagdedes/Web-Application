<?php

class Account
{

    public const
        IGNORE_APPLICATION = -1,
        BIGMANAGE_APPLICATION = 1;

    private object $object;
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
    private AccountIdentification $identification;
    private AccountNotifications $notifications;
    private AccountPhoneNumber $phoneNumber;
    private AccountPatreon $patreon;
    private AccountProduct $product;
    private AccountGiveaway $giveaway;
    private AccountFunctionality $functionality;
    private AccountStatistics $statistics;
    private AccountRegistry $registry;
    private AccountSession $session;
    private TwoFactorAuthentication $twoFactorAuthentication;
    private PaymentProcessor $paymentProcessor;
    private AccountInstructions $instructions;
    private AccountTeam $team;
    private AccountTranslation $translation;

    public function __construct(?int    $applicationID = null,
                                ?int    $id = null,
                                ?string $email = null,
                                ?string $username = null,
                                ?string $identification = null,
                                bool    $checkDeletion = true,
                                ?string $attemptCreation = null)
    {
        // Dependent
        $this->settings = new AccountSettings($this);
        $this->history = new AccountHistory($this);
        $this->transactions = new AccountTransactions($this);
        $this->purchases = new AccountPurchases($this);
        $this->cooldowns = new AccountCooldowns($this);
        $this->accounts = new AccountAccounts($this);
        $this->permissions = new AccountPermissions($this);
        $this->notifications = new AccountNotifications($this);
        $this->phoneNumber = new AccountPhoneNumber($this);
        $this->patreon = new AccountPatreon($this);
        $this->team = new AccountTeam($this);
        $this->translation = new AccountTranslation($this);

        // Partial
        $this->email = new AccountEmail($this);
        $this->actions = new AccountActions($this);
        $this->password = new AccountPassword($this);
        $this->downloads = new AccountProductDownloads($this);
        $this->giveaway = new AccountGiveaway($this);
        $this->moderations = new AccountModerations($this);
        $this->functionality = new AccountFunctionality($this);
        $this->instructions = new AccountInstructions($this);
        $this->statistics = new AccountStatistics($this);

        // Independent
        $this->product = new AccountProduct($this);
        $this->registry = new AccountRegistry($this);
        $this->session = new AccountSession($this);
        $this->twoFactorAuthentication = new TwoFactorAuthentication($this);
        $this->paymentProcessor = new PaymentProcessor($this);

        // Transform
        $this->transformLocal(
            $applicationID,
            $id,
            $email,
            $username,
            $identification,
            $checkDeletion,
            $attemptCreation
        );
    }

    private function transformLocal(?int    $applicationID = null,
                                    ?int    $id = null,
                                    ?string $email = null,
                                    ?string $username = null,
                                    ?string $identification = null,
                                    bool    $checkDeletion = true,
                                    ?string $attemptCreation = null): void
    {
        $hasID = $id !== null;
        $hasUsername = $username !== null;
        $hasIdentification = $identification !== null;
        $hasEmail = is_email($email);

        if (!$hasIdentification
            && ($hasID ? $id <= 0 : !$hasUsername && !$hasEmail)) {
            $this->def($applicationID);
        } else {
            if ($hasIdentification) {
                $query = get_sql_query(
                    AccountVariables::ACCOUNT_IDENTIFICATION_TABLE,
                    array("account_id"),
                    array(
                        array("code", $identification)
                    ),
                    null,
                    1
                );

                if (empty($query)) {
                    $runQuery = false;
                } else {
                    $id = $query[0]->account_id;
                    $hasID = true;
                    $runQuery = true;
                }
            } else {
                $runQuery = true;

                if ($attemptCreation !== null && $hasEmail) {
                    $this->getRegistry()->create(
                        $email,
                        isset($attemptCreation[0]) ? $attemptCreation : null,
                        $hasUsername ? $username : null
                    );
                }
            }
            if ($runQuery) {
                $query = get_sql_query(
                    AccountVariables::ACCOUNTS_TABLE,
                    null,
                    array(
                        $hasID ? array("id", $id) : "",
                        $hasEmail ? array("email_address", strtolower($email)) : "",
                        $hasUsername ? array("name", $username) : "",
                        $checkDeletion ? array("deletion_date", null) : "",
                        $applicationID !== self::IGNORE_APPLICATION ? array("application_id", $applicationID) : ""
                    ),
                    null,
                    1
                );
            } else {
                $query = null;
            }

            if (!empty($query)) {
                $this->identification = new AccountIdentification($this);

                if ($hasIdentification && $this->identification->get() != $identification) { // Expired
                    $this->def($applicationID);
                } else {
                    $this->exists = true;
                    $this->object = $query[0];
                }
            } else {
                $this->def($applicationID);
            }
        }
    }

    public function transform(?int    $id = null,
                              ?string $email = null,
                              ?string $username = null,
                              ?string $identification = null,
                              bool    $checkDeletion = true,
                              ?string $attemptCreation = null): self
    {
        $this->transformLocal(
            $this->getDetail("application_id"),
            $id,
            $email,
            $username,
            $identification,
            $checkDeletion,
            $attemptCreation
        );
        return $this;
    }

    private function def(?int $applicationID): void
    {
        $this->exists = false;
        $this->object = new stdClass();
        $this->object->application_id = $applicationID;
    }

    public function getNew(?int $id = null, ?string $email = null, $username = null,
                                $identification = null,
                           bool $checkDeletion = true): self
    {
        $account = new self(
            $this->getDetail("application_id"),
            $id,
            $email,
            $username,
            $identification,
            $checkDeletion,
        );

        $account->getSession()->setCustomKey(
            $this->session->getType(),
            $this->session->getCustomKey()
        );
        return $account;
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
            if (!set_sql_query(
                AccountVariables::ACCOUNTS_TABLE,
                array($detail => $value),
                array(
                    array("id", $this->object->id)
                ),
                null,
                1
            )) {
                return new MethodReply(false, "Failed to interact with the database.");
            }
            $this->object->{$detail} = $value;
        }
        return new MethodReply(true);
    }

    public function getObject(): ?object
    {
        return $this->object;
    }

    // Separator

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

    public function getTranslation(): AccountTranslation
    {
        return $this->translation;
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

    public function getPatreon(): AccountPatreon
    {
        return $this->patreon;
    }

    public function getProductGiveaway(): AccountGiveaway
    {
        return $this->giveaway;
    }

    public function getFunctionality(): AccountFunctionality
    {
        return $this->functionality;
    }

    public function getStatistics(): AccountStatistics
    {
        return $this->statistics;
    }

    // Separator

    public function getProduct(): AccountProduct
    {
        return $this->product;
    }

    public function getSession(): AccountSession
    {
        return $this->session;
    }

    public function getTwoFactorAuthentication(): TwoFactorAuthentication
    {
        return $this->twoFactorAuthentication;
    }

    public function getRegistry(): AccountRegistry
    {
        return $this->registry;
    }

    public function getTeam(): AccountTeam
    {
        return $this->team;
    }

    public function getPaymentProcessor(): PaymentProcessor
    {
        return $this->paymentProcessor;
    }

    public function getInstructions(): AccountInstructions
    {
        return $this->instructions;
    }

    // Separator

    public function refresh(): void
    {
        if ($this->exists()) {
            if (isset($this->transactions)) {
                $this->transactions->getSuccessful();
            }
            if (isset($this->email)
                && !$this->email->isVerified()) {
                $this->email->initiateVerification(null, $this->session->isCustom());
            }
        }
    }
}
