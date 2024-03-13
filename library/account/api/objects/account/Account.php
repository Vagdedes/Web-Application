<?php

class Account
{
    public const
        LOAD_BALANCER_IP = "10.0.0.3",
        IMAGES_PATH = "https://vagdedes.com/.images/",
        WEBSITE_DESIGN_PATH = "https://vagdedes.com/.css/",
        DOWNLOADS_PATH = "/var/www/vagdedes/.temporary/";

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
    private AccountProduct $product;
    private AccountGiveaway $giveaway;
    private AccountFunctionality $functionality;
    private AccountWallet $wallet;
    private AccountStatistics $statistics;
    private AccountReference $reference;
    private AccountFeedback $feedback;
    private AccountRegistry $registry;
    private AccountSession $session;
    private TwoFactorAuthentication $twoFactorAuthentication;
    private WebsiteKnowledge $knowledge;
    private LanguageTranslation $translation;
    private PaymentProcessor $paymentProcessor;
    private AccountInstructions $instructions;

    public const IGNORE_APPLICATION = -1;

    public function __construct(?int    $applicationID = null,
                                ?int    $id = null, ?string $email = null, ?string $username = null,
                                ?string $identification = null,
                                bool    $checkDeletion = true,
                                bool    $cache = true)
    {
        $hasID = $id !== null;
        $hasUsername = $username !== null;
        $hasIdentification = $identification !== null;

        if (!$hasIdentification
            && ($hasID ? $id <= 0 : !$hasUsername && !is_email($email))) {
            $this->exists = false;
            $this->object = new stdClass();
            $this->object->application_id = $applicationID;
        } else {
            if ($hasIdentification) {
                global $account_identification_table;

                if ($cache) {
                    set_sql_cache(null, self::class);
                }
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

            if ($cache) {
                set_sql_cache(null, self::class);
            }
            $query = get_sql_query(
                $accounts_table,
                null,
                array(
                    $hasID ? array("id", $id) : "",
                    $email !== null ? array("email_address", strtolower($email)) : "",
                    $hasUsername ? array("name", $username) : "",
                    $checkDeletion ? array("deletion_date", null) : "",
                    $applicationID !== self::IGNORE_APPLICATION ? array("application_id", $applicationID) : ""
                ),
                null,
                1
            );

            if (!empty($query)) {
                $this->exists = true;
                $this->object = $query[0];
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
            } else {
                $this->exists = false;
                $this->object = new stdClass();
                $this->object->application_id = $applicationID;
            }
        }
        // Standalone
        $this->email = new AccountEmail($this);
        $this->actions = new AccountActions($this);
        $this->password = new AccountPassword($this);
        $this->downloads = new AccountProductDownloads($this);
        $this->team = new AccountTeam($this);
        $this->files = new AccountFiles($this);
        $this->affiliate = new AccountAffiliate($this);
        $this->cooperation = new AccountCooperation($this);
        $this->communication = new AccountCommunication($this);
        $this->giveaway = new AccountGiveaway($this);
        $this->moderations = new AccountModerations($this);
        $this->functionality = new AccountFunctionality($this);
        $this->wallet = new AccountWallet($this);
        $this->reference = new AccountReference($this);
        $this->feedback = new AccountFeedback($this);
        $this->instructions = new AccountInstructions($this);
        $this->statistics = new AccountStatistics($this);

        // Independent
        $this->product = new AccountProduct($this);
        $this->registry = new AccountRegistry($this);
        $this->session = new AccountSession($this);
        $this->twoFactorAuthentication = new TwoFactorAuthentication($this);
        $this->knowledge = new WebsiteKnowledge($applicationID);
        $this->translation = new LanguageTranslation($applicationID);
        $this->paymentProcessor = new PaymentProcessor($applicationID);
    }

    public function getNew(?int $id = null, ?string $email = null, $username = null,
                                $identification = null,
                           bool $checkDeletion = true,
                           bool $cache = true): self
    {
        $account = new self(
            $this->getDetail("application_id"),
            $id,
            $email,
            $username,
            $identification,
            $checkDeletion,
            $cache
        );
        $account->getSession()->setCustomKey(
            $this->getSession()->getType(),
            $this->getSession()->getCustomKey()
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

    public function getProductGiveaway(): AccountGiveaway
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

    public function getFeedback(): AccountFeedback
    {
        return $this->feedback;
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

    public function getLanguageTranslation(): LanguageTranslation
    {
        return $this->translation;
    }

    public function getWebsiteKnowledge(): WebsiteKnowledge
    {
        return $this->knowledge;
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
                $this->getTransactions()->clearCache();
                $this->getTransactions()->getSuccessful();
            }
            if (isset($this->email)
                && !$this->getEmail()->isVerified()) {
                $this->getEmail()->initiateVerification(null, $this->session->isCustom());
            }
        }
    }

    public function clearMemory(mixed $key = null, ?callable $callable = null): void
    {
        if (isset($this->object->id)
            && isset($this->object->name)
            && isset($this->object->email_address)) {
            $potentialKeys = 4;

            if ($key === null) {
                clear_memory(
                    array(
                        get_sql_cache_key("account_id", $this->object->id),
                        get_sql_cache_key("id", $this->object->id),
                        get_sql_cache_key("name", $this->object->name),
                        get_sql_cache_key("email_address", $this->object->email_address)
                    ),
                    true,
                    $potentialKeys,
                    $callable
                );
            } else if (is_array($key)) {
                $key1 = get_sql_cache_key("account_id", $this->object->id);
                $key2 = get_sql_cache_key("id", $this->object->id);
                $key3 = get_sql_cache_key("name", $this->object->name);
                $key4 = get_sql_cache_key("email_address", $this->object->email_address);

                foreach ($key as $item) {
                    clear_memory(
                        array(
                            array($item, $key1),
                            array($item, $key2),
                            array($item, $key3),
                            array($item, $key4)
                        ),
                        true,
                        $potentialKeys,
                        $callable
                    );
                }
            } else {
                clear_memory(
                    array(array(
                        $key,
                        get_sql_cache_key("account_id", $this->object->id)
                    ), array(
                        $key,
                        get_sql_cache_key("id", $this->object->id)
                    ), array(
                        $key,
                        get_sql_cache_key("name", $this->object->name)
                    ), array(
                        $key,
                        get_sql_cache_key("email_address", $this->object->email_address)
                    )),
                    true,
                    $potentialKeys,
                    $callable
                );
            }
        } else if ($key !== null) {
            clear_memory(array($key), true, 1);
        }
    }
}
