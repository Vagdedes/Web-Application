<?php

class AccountProductDownloads
{
    private Account $account;

    private const
        DEFAULT_COOLDOWN = "2 seconds";

    public const
        TOKEN_SEARCH_SECONDS = 60,
        TOKEN_SEARCH_LIMIT = 30;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    private function findDownloadableFile(array $files): MethodReply
    {
        if (empty($files)) {
            return new MethodReply(false, "No files available for download.");
        }
        $found = null;

        foreach ($files as $file) {
            if ($file->required_permission !== null) {
                if (!$this->account->getPermissions()->hasPermission($file->required_permission)) {
                    return new MethodReply(true, null, $file);
                }
            } else {
                $found = $file;
            }
        }
        return $found !== null ?
            new MethodReply(true, null, $found) :
            new MethodReply(false, "No download available for you currently.");
    }

    private function calculateDuration(int|string|null $customExpiration): string
    {
        return $customExpiration !== null ? get_future_date($customExpiration) : get_future_date("3 months");
    }

    private function calculateToken(): string
    {
        return strtoupper(random_string(8));
    }

    private function checkAndUpdateDownloadCount(string|object $tokenOrQuery): MethodReply
    {
        global $product_downloads_table;
        if (is_string($tokenOrQuery)) {
            $query = get_sql_query(
                $product_downloads_table,
                array("id", "download_count", "max_downloads"),
                array(
                    array("token", $tokenOrQuery),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                null,
                1
            );
        } else {
            $query = $tokenOrQuery;
        }

        if (!empty($query)) {
            $query = $query[0];

            if ($query->max_downloads !== null) {
                $downloadCount = empty($query->download_count) ? 0 : $query->download_count;

                if ($downloadCount >= $query->max_downloads) {
                    return new MethodReply(false, "Download token has reached its max download limit.");
                } else if (!set_sql_query(
                    $product_downloads_table,
                    array(
                        "download_count" => ($downloadCount + 1),
                    ),
                    array(
                        array("id", $query->id)
                    ),
                    null,
                    1
                )) {
                    return new MethodReply(false, "Failed to interact with the database.");
                }
            }
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Download token not found or has expired.");
        }
    }

    private function sendFile(bool            $exists,
                              int|string      $token,
                              object          $fileProperties,
                              object          $productObject,
                              int|string      $requestedByToken = null,
                              int|string|null $maxDownloads = null,
                              int|string|null $customExpiration = null): MethodReply
    {
        global $product_downloads_table;
        $originalFile = "/var/www/.structure/downloadable/" . $fileProperties->file_name . "." . $fileProperties->file_type;

        if (!file_exists($originalFile)) {
            return new MethodReply(false, "Failed to find original file.");
        }
        $fileCopy = Account::DOWNLOADS_PATH
            . ($fileProperties->file_rename !== null ? $fileProperties->file_rename : $fileProperties->file_name)
            . $token . "." . $fileProperties->file_type;

        if (!copy($originalFile, $fileCopy)) {
            $errors = error_get_last();
            return new MethodReply(
                false,
                isset($errors["message"]) ?
                    "Failed to prepare file copy: " . $errors["message"] :
                    "Failed to prepare file copy."
            );
        }
        if (!file_exists($fileCopy)) {
            return new MethodReply(false, "Failed to find copied file.");
        }
        if ($exists) {
            $update = $this->checkAndUpdateDownloadCount(
                $requestedByToken !== null ? $requestedByToken : $token
            );

            if (!$update->isPositiveOutcome()) {
                return $update;
            }
        } else {
            if (!sql_insert(
                $product_downloads_table,
                array(
                    "account_id" => $this->account->getDetail("id"),
                    "product_id" => $productObject->id,
                    "token" => $token,
                    "requested_by_token" => $requestedByToken,
                    "download_count" => $maxDownloads !== null ? 1 : null,
                    "max_downloads" => $maxDownloads,
                    "creation_date" => get_current_date(),
                    "expiration_date" => $this->calculateDuration($customExpiration)
                ))) {
                unlink($fileCopy);
                return new MethodReply(false, "Failed to interact with the database.");
            }
        }
        if ($this->account->exists()) {
            if (!$this->account->getHistory()->add(
                "download_file" . ($requestedByToken !== null ? "_by_token" : ""),
                $requestedByToken,
                $token
            )) {
                unlink($fileCopy);
                return new MethodReply(false, "Failed to update user history.");
            }
            if ($requestedByToken === null) {
                $this->account->getPhoneNumber()->send(
                    "notification",
                    array(
                        "notification" => "The product '" . $productObject->name . "' was downloaded using your account."
                            . " If this was not you, please change the responsible password immediately."
                    )
                );
            }
        }
        $this->account->clearMemory(self::class, function ($value) {
            return is_array($value);
        });
        send_file_download($fileCopy, false);
        unlink($fileCopy);
        exit();
    }

    // Separator

    public function findOrCreate(int|string      $productID,
                                 int|string|null $maxDownloads = null,
                                 bool            $sendFile = true,
                                 int|string|null $customExpiration = null,
                                 int|string|null $cooldown = self::DEFAULT_COOLDOWN): MethodReply
    {
        global $product_downloads_table;
        $query = get_sql_query(
            $product_downloads_table,
            array("token", "max_downloads", "download_count"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("product_id", $productID),
                array("deletion_date", null),
                null,
                array("max_downloads", "IS", null, 0),
                array("download_count", "<", "max_downloads"),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (!empty($query)) {
            return new MethodReply(true, "Successfully found token.", $query[0]->token);
        }
        return $this->create($productID, null, $maxDownloads, $sendFile, $customExpiration, $cooldown);
    }

    public function create(int|string      $productID,
                           int|string      $requestedByToken = null,
                           int|string|null $maxDownloads = null,
                           bool            $sendFile = true,
                           int|string|null $customExpiration = null,
                           int|string|null $cooldown = self::DEFAULT_COOLDOWN): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::DOWNLOAD_PRODUCT, true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        global $product_downloads_table;
        $hasToken = $requestedByToken !== null;

        if ($hasToken) {
            $functionalityAlternative = $functionality->getResult(AccountFunctionality::AUTO_UPDATER);

            if (!$functionalityAlternative->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
            $update = $this->checkAndUpdateDownloadCount($requestedByToken);

            if (!$update->isPositiveOutcome()) {
                return $update;
            }
        }
        $product = $this->account->getProduct()->find($productID);

        if (!$product->isPositiveOutcome()) {
            return new MethodReply(false, $product->getMessage());
        }
        $product = $product->getObject()[0];

        if ($this->account->exists()) {
            $purchase = $this->account->getPurchases()->owns($productID);

            if (!$purchase->isPositiveOutcome()) {
                return new MethodReply(false, "You do not own this product.");
            }
        }
        $fileProperties = $this->findDownloadableFile($product->downloads);

        if (!$fileProperties->isPositiveOutcome()) {
            return new MethodReply(false, $fileProperties->getMessage());
        }
        $fileProperties = $fileProperties->getObject();

        if (!$this->account->getEmail()->isVerified()) {
            return new MethodReply(false, "Verify your email before downloading.");
        }
        if ($cooldown !== null) {
            $functionality->addInstantCooldown(AccountFunctionality::DOWNLOAD_PRODUCT, $cooldown);
        }
        $newToken = $this->calculateToken();

        while (true) {
            if (empty(get_sql_query(
                $product_downloads_table,
                array("id"),
                array(
                    array("token", $newToken)
                ),
                null,
                1
            ))) {
                break;
            }
            $newToken = $this->calculateToken();
        }
        $duration = $this->calculateDuration($customExpiration);

        if ($sendFile) {
            return $this->sendFile(
                false,
                $newToken,
                $fileProperties,
                $product,
                $requestedByToken,
                $maxDownloads,
                $customExpiration
            );
        } else if (!sql_insert(
            $product_downloads_table,
            array(
                "account_id" => $this->account->getDetail("id"),
                "product_id" => $productID,
                "token" => $newToken,
                "requested_by_token" => $requestedByToken,
                "max_downloads" => $maxDownloads,
                "creation_date" => get_current_date(),
                "expiration_date" => $duration
            ))) {
            return new MethodReply(false, "Failed to interact with the database.");
        } else {
            return new MethodReply(true, "Download token successfully created.", $newToken);
        }
    }

    public function find(int|string      $token,
                         bool            $sendFile = true,
                         int|string|null $cooldown = self::DEFAULT_COOLDOWN): MethodReply
    {
        global $product_downloads_table;
        $query = get_sql_query(
            $product_downloads_table,
            null,
            array(
                array("token", $token),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $product = $this->account->getProduct()->find($query->product_id);

            if (!$product->isPositiveOutcome()) {
                return new MethodReply(false, $product->getMessage());
            }
            $product = $product->getObject()[0];

            if ($this->account->exists()) {
                $purchase = $this->account->getPurchases()->owns($query->product_id);

                if (!$purchase->isPositiveOutcome()) {
                    return new MethodReply(false, "You do not own this product.");
                }
            }
            if ($sendFile) {
                $fileProperties = $this->findDownloadableFile($product->downloads);

                if (!$fileProperties->isPositiveOutcome()) {
                    return new MethodReply(false, $fileProperties->getMessage());
                }
                $functionality = $this->account->getFunctionality();
                $functionalityOutcome = $functionality->getResult(AccountFunctionality::DOWNLOAD_PRODUCT, true);

                if (!$functionalityOutcome->isPositiveOutcome()) {
                    return new MethodReply(false, $functionalityOutcome->getMessage());
                }
                $fileProperties = $fileProperties->getObject();

                if ($cooldown !== null) {
                    $functionality->addInstantCooldown(AccountFunctionality::DOWNLOAD_PRODUCT, $cooldown);
                }
                return $this->sendFile(
                    true,
                    $query->token,
                    $fileProperties,
                    $product,
                    null,
                    $query->max_downloads,
                    null
                );
            } else {
                $query->account = new Account(Account::IGNORE_APPLICATION, $query->account_id);
                return new MethodReply(true, null, $query);
            }
        } else {
            return new MethodReply(false);
        }
    }

    // Separator

    public function getList(bool $active = false, int $limit = 0): array
    {
        global $product_downloads_table;
        set_sql_cache(self::class);
        return get_sql_query(
            $product_downloads_table,
            null,
            array(
                array("account_id", $this->account->getDetail("id")),
                $active ? array("expiration_date", ">", get_current_date()) : "",
            ),
            null,
            $limit
        );
    }

    public
    function getCount(bool $active = false, int $limit = 0): int
    {
        global $product_downloads_table;
        set_sql_cache(self::class);
        return sizeof(
            get_sql_query(
                $product_downloads_table,
                array("id"),
                array(
                    array("account_id", $this->account->getDetail("id")),
                    $active ? array("expiration_date", ">", get_current_date()) : "",
                ),
                null,
                $limit
            )
        );
    }

    public function has(bool $active = false): bool
    {
        return $this->getCount($active, 1) > 0;
    }

}
