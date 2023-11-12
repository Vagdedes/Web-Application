<?php

class ProductCoupon
{
    private ?float $discount;

    public function __construct(int|float|string $name, int|string $accountID, int|string $productID)
    {
        global $product_coupons_table;
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            $product_coupons_table,
            array("discount", "uses"),
            array(
                array("name", $name),
                array("product_id", $productID),
                array("deletion_date", null),
                array("creation_date", "IS NOT", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null,
                null,
                array("account_id", "=", $accountID, 0),
                array("account_id", null),
                null,
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            $this->discount = null;
        } else {
            global $product_purchases_table;
            $query = $query[0];
            $uses = $query->uses;
            $discount = $query->discount;
            set_sql_cache(null, self::class);
            $query = get_sql_query(
                $product_purchases_table,
                array("id"),
                array(
                    array("coupon", $name)
                ),
                null,
                $uses
            );

            if ($uses - sizeof($query) > 0) {
                $this->discount = $discount;
            } else {
                $this->discount = null;
            }
        }
    }

    public function canUse(): bool
    {
        return $this->discount !== null;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function getDecimalMultiplier(): float
    {
        return 1.0 - ($this->discount / 100.0);
    }
}
