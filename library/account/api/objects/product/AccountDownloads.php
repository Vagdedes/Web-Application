<?php

class AccountDownloads
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    private function findDownloadableFile($files): MethodReply
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

    public function sendFileDownload($productID, $requestedByToken = null): MethodReply
    {
        $functionality = $this->account->getFunctionality();
        $functionalityOutcome = $functionality->getResult(AccountFunctionality::DOWNLOAD_PRODUCT,true);

        if (!$functionalityOutcome->isPositiveOutcome()) {
            return new MethodReply(false, $functionalityOutcome->getMessage());
        }
        $hasToken = $requestedByToken !== null;

        if ($hasToken) {
            $functionalityAlternative = $functionality->getResult(AccountFunctionality::AUTO_UPDATER);

            if (!$functionalityAlternative->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        $product = $this->account->getProduct()->find($productID);

        if (!$product->isPositiveOutcome()) {
            return new MethodReply(false, $product->getMessage());
        }
        $fileProperties = $this->findDownloadableFile($product->getObject()[0]->downloads);

        if (!$fileProperties->isPositiveOutcome()) {
            return new MethodReply(false, $fileProperties->getMessage());
        }
        $fileProperties = $fileProperties->getObject();

        if (!$this->account->getEmail()->isVerified()) {
            return new MethodReply(false, "Verify your email before downloading.");
        }
        global $product_downloads_table;
        $functionality->addUserCooldown(AccountFunctionality::DOWNLOAD_PRODUCT, "2 seconds");
        $downloadTokenLength = 8;
        $newToken = strtoupper(random_string($downloadTokenLength));

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
            $newToken = strtoupper(random_string($downloadTokenLength));
        }
        $originalFile = "/var/www/.structure/downloadable/" . $fileProperties->file_name . "." . $fileProperties->file_type;

        if (!file_exists($originalFile)) {
            return new MethodReply(false, "Failed to find original file.");
        }
        $fileCopy = "/var/www/" . get_potential_directory() . "/.temporary/" . $fileProperties->file_name . $newToken . "." . $fileProperties->file_type;

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
        if (!sql_insert(
            $product_downloads_table,
            array(
                "account_id" => $this->account->getDetail("id"),
                "product_id" => $productID,
                "token" => $newToken,
                "requested_by_token" => $requestedByToken,
                "creation_date" => date("Y-m-d H:i:s"),
                "expiration_date" => get_future_date("3 months")
            ))) {
            unlink($fileCopy);
            return new MethodReply(false, "Failed to interact with the database.");
        }
        if (!$this->account->getHistory()->add(
            "download_file" . ($hasToken ? "_by_token" : ""),
            $requestedByToken,
            $newToken
        )) {
            unlink($fileCopy);
            return new MethodReply(false, "Failed to update user history.");
        }
        clear_memory(array(self::class, AccountDownloads::class), true);
        send_file_download($fileCopy, false);
        unlink($fileCopy);
        exit();
    }

    public function getList($active = false, $limit = 0): array
    {
        global $product_downloads_table;
        set_sql_cache(null, self::class);
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

    public function getCount($active = false, $limit = 0): int
    {
        global $product_downloads_table;
        set_sql_cache(null, self::class);
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

    public function has($active = false): bool
    {
        return $this->getCount($active, 1) > 0;
    }

    public function verify($token, $active = false): bool
    {
        global $product_downloads_table;
        set_sql_cache(null, self::class);
        return !empty(get_sql_query(
            $product_downloads_table,
            array("id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                $active ? array("expiration_date", ">", get_current_date()) : "",
                array("token", $token)
            ),
            null,
            1
        ));
    }

    public function find($token): MethodReply
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
            $query->account = new Account(Account::IGNORE_APPLICATION, $query->account_id);
            return new MethodReply(true, null, $query);
        } else {
            return new MethodReply(false);
        }
    }
}
