<?php

class ProductDownload
{
    private ?object $object;

    public function __construct($token)
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
            $this->object = $query[0];
        } else {
            $this->object = null;
        }
    }

    public function getProperties(): ?object
    {
        return $this->object;
    }

    public function found(): bool
    {
        return $this->object !== null;
    }

    public function getAccount(): Account
    {
        return new Account(Account::IGNORE_APPLICATION, $this->object->account_id);
    }
}