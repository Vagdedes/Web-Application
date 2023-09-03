<?php

class RegisteredBuyers
{
    private int $count;

    public function __construct($productID)
    {
        global $product_purchases_table;
        set_sql_cache(null, self::class);
        $this->count = sizeof(get_sql_query(
            $product_purchases_table,
            array("id"),
            array(
                array("product_id", $productID),
                array("deletion_date", null)
            )
        ));
    }

    public function get(): int
    {
        return $this->count;
    }
}
