<?php

class WebsiteProduct
{
    private array $products;

    public function __construct($applicationID, $documentation = true, $productID = null)
    {
        $cacheKey = array(
            $this,
            $documentation,
            $productID
        );
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            $this->products = $cache;
            return;
        }
        global $products_table;
        $hasProductID = $productID !== null;
        $array = get_sql_query($products_table,
            null,
            array(
                array("application_id", $applicationID),
                array("deletion_date", null),
                $hasProductID ? array("id", $productID) : ""
            ),
            $hasProductID ? null
                : array(
                "DESC",
                "priority"
            )
        );

        if (!empty($array)) {
            global $website_url, $product_buttons_table,
                   $product_compatibilities_table,
                   $product_transaction_search_table,
                   $product_updates_table,
                   $product_identification_table,
                   $product_divisions_table;

            foreach ($array as $arrayKey => $object) {
                $productID = $object->id;
                $object->transaction_search = get_sql_query(
                    $product_transaction_search_table,
                    array(
                        "transaction_key",
                        "transaction_value",
                        "identification_method",
                        "ignore_case",
                        "additional_products",
                        "duration",
                        "email",
                        "accepted_account_id",
                        "lookup_id"
                    ),
                    array(
                        array("product_id", $productID),
                        array("deletion_date", null),
                    ),
                    array(
                        "ASC",
                        "lookup_id"
                    )
                );

                if (!$documentation && empty($object->transaction_search)) {
                    unset($array[$arrayKey]);
                    continue;
                }
                $object->registered_buyers = new RegisteredBuyers($productID);
                $object->registered_buyers = $object->registered_buyers->get();
                $object->currency = "EUR";
                $object->url = $website_url . "/viewProduct/?id=" . $productID;
                $query = get_sql_query(
                    $product_divisions_table,
                    array("family", "name", "description", "no_html"),
                    array(
                        array("product_id", $productID),
                        array("deletion_date", null)
                    )
                );

                if (!empty($query)) {
                    $divisions = array();

                    foreach ($query as $division) {
                        if (array_key_exists($division->family, $divisions)) {
                            $divisions[$division->family][] = $division;
                        } else {
                            $divisions[$division->family] = array($division);
                        }
                    }
                    $object->divisions = $divisions;
                } else {
                    $object->divisions = $query;
                }
                $object->compatibilities = get_sql_query(
                    $product_compatibilities_table,
                    array("compatible_product_id"),
                    array(
                        array("product_id", $productID),
                        array("deletion_date", null),
                    )
                );

                if (!empty($object->compatibilities)) {
                    foreach ($object->compatibilities as $key => $value) {
                        $object->compatibilities[$key] = $value->compatible_product_id;
                    }
                }
                $object->buttons = new stdClass();
                $object->buttons->pre_purchase = get_sql_query(
                    $product_buttons_table,
                    array("color", "name", "url", "requires_account"),
                    array(
                        array("product_id", $productID),
                        array("deletion_date", null),
                        array("post_purchase", null)
                    )
                );
                $object->buttons->post_purchase = get_sql_query(
                    $product_buttons_table,
                    array("color", "name", "url", "requires_account"),
                    array(
                        array("product_id", $productID),
                        array("deletion_date", null),
                        array("post_purchase", "IS NOT", null)
                    )
                );
                $object->downloads = get_sql_query(
                    $product_updates_table,
                    array(
                        "file_name",
                        "file_type",
                        "required_permission",
                        "version",
                        "note",
                        "name",
                        "description"
                    ),
                    array(
                        array("product_id", $productID),
                        array("creation_date", "IS NOT", null),
                        array("deletion_date", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null,
                    ),
                    "version DESC, required_permission DESC",
                    10
                );
                $object->download_note = null;

                if (empty($object->downloads)) {
                    $object->latest_version = null;
                    $object->minimum_supported_version = null;
                    $object->supported_versions = array();
                } else {
                    $downloads = array();
                    $object->supported_versions = array();

                    foreach ($object->downloads as $value) {
                        $hash = string_to_integer($value->file_name);
                        $hash = overflow_integer(($hash * 31) + string_to_integer($value->file_type));
                        $hash = overflow_integer(($hash * 31) + string_to_integer($value->required_permission));
                        $downloads[$hash] = $value;

                        if (!in_array($value->version, $object->supported_versions)) {
                            $object->supported_versions[] = $value->version;
                        }
                        if ($object->download_note === null) {
                            $object->download_note = $value->note;
                        }
                    }
                    $downloads = array_values($downloads);
                    $object->downloads = $downloads;
                    $object->latest_version = $downloads[0]->version;
                    $object->minimum_supported_version = $downloads[sizeof($downloads) - 1]->version;
                }
                $object->identification = get_sql_query(
                    $product_identification_table,
                    array(
                        "accepted_account_id",
                        "accepted_platform_product_id"
                    ),
                    array(
                        array("product_id", $productID),
                    )
                );

                if (!empty($object->identification)) {
                    $identifications = array();

                    foreach ($object->identification as $identification) {
                        $identifications[$identification->accepted_account_id] = $identification->accepted_platform_product_id;
                    }
                    $object->identification = $identifications;
                }
                if (!$hasProductID) {
                    $cacheKeyCopy = $cacheKey;
                    $cacheKeyCopy[] = $productID;
                    set_key_value_pair($cacheKeyCopy, array($object), "1 minute"); // Update individual cache conveniently
                }
            }
        }
        set_key_value_pair($cacheKey, $array, "1 minute");
        $this->products = $array;
    }

    public function getResults(): array
    {
        return $this->products;
    }

    public function getFirstResult(): ?object
    {
        return array_shift($this->products);
    }

    public function found(): bool
    {
        return !empty($this->products);
    }
}
