<?php

class AccountProduct
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function find($productID = null, $documentation = true, $accountID = null): MethodReply
    {
        $applicationID = $this->account->getDetail("application_id");
        $hasAccount = $accountID !== null;
        $hasProduct = !$hasAccount && $productID !== null;

        if ($hasProduct) {
            $functionality = $this->account->getFunctionality();
            $functionalityOutcome = $functionality->getResult(AccountFunctionality::VIEW_PRODUCT);

            if (!$functionalityOutcome->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        $cacheKey = array(
            $this,
            $documentation,
            $productID,
        );
        $array = get_key_value_pair($cacheKey);

        if (!is_array($array)) {
            global $products_table, $sql_max_cache_time;
            $array = get_sql_query($products_table,
                null,
                array(
                    $hasAccount ? array("account_id", $accountID) : array("application_id", $applicationID),
                    array("deletion_date", null),
                    $hasProduct ? array("id", $productID) : ""
                ),
                $hasProduct ? null
                    : array(
                    "DESC",
                    "priority"
                )
            );

            if (!empty($array)) {
                global $website_url,
                       $product_buttons_table,
                       $product_compatibilities_table,
                       $product_transaction_search_table,
                       $product_updates_table,
                       $product_identification_table,
                       $product_divisions_table,
                       $product_cards_table,
                       $product_purchases_table,
                       $product_reviews_table,
                       $product_tiers_table;
                $accountExists = $this->account->exists();

                foreach ($array as $arrayKey => $object) {
                    if (!$accountExists || true) {
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
                                "lookup_id",
                                "tier_id"
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
                        $object->reviews = get_sql_query(
                            $product_reviews_table,
                            array("account_id", "ratio", "name", "description", "creation_date"),
                            array(
                                array("product_id", $productID),
                                array("deletion_date", null),
                            )
                        );
                        $object->tiers = new stdClass();
                        $object->tiers->free = array();
                        $object->tiers->paid = array();
                        $object->tiers->all = get_sql_query(
                            $product_tiers_table,
                            array("id", "name", "price", "currency", "required_permission", "required_products"),
                            array(
                                array("product_id", $productID),
                                array("deletion_date", null),
                            ),
                            array(
                                "ASC",
                                "price"
                            )
                        );
                        $object->is_free = true;

                        if (!empty($object->tiers->all)) {
                            foreach ($object->tiers->all as $tier) {
                                if ($tier->price === null) {
                                    $object->tiers->free[] = $tier;
                                } else {
                                    $object->is_free = false;
                                    $object->tiers->paid[] = $tier;
                                }
                            }
                        }
                        if ($object->patreon_tiers === null) {
                            $object->patreon_tiers = array();
                        } else {
                            $object->patreon_tiers = explode("|", $object->patreon_tiers);
                        }
                        if ($object->is_free) {
                            $object->registered_buyers = 0;
                        } else {
                            $object->registered_buyers = sizeof(get_sql_query(
                                $product_purchases_table,
                                array("id"),
                                array(
                                    array("product_id", $productID),
                                    array("deletion_date", null)
                                )
                            ));
                            if (!empty($object->patreon_tiers)) {
                                $object->registered_buyers += sizeof(get_patreon2_subscriptions(null, $object->patreon_tiers));
                            }
                        }
                        $object->url = $website_url . "/viewProduct/?id=" . $productID;
                        $object->download_url = $website_url . "/downloadURL/?id=" . $productID;
                        $object->divisions = new stdClass();
                        $object->buttons = new stdClass();
                        $object->cards = new stdClass();

                        foreach (array(
                                     "pre_purchase" => null,
                                     "post_purchase" => 1,
                                 ) as $key => $value) {
                            $query = get_sql_query(
                                $product_divisions_table,
                                array("family", "name", "description", "no_html"),
                                array(
                                    array("product_id", $productID),
                                    array("deletion_date", null),
                                    null,
                                    array("post_purchase", "=", 2, 0),
                                    array("post_purchase", $value),
                                    null,
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
                                $object->divisions->{$key} = $divisions;
                            } else {
                                $object->divisions->{$key} = $query;
                            }
                            $object->buttons->{$key} = get_sql_query(
                                $product_buttons_table,
                                array("color", "name", "url", "requires_account"),
                                array(
                                    array("product_id", $productID),
                                    array("deletion_date", null),
                                    null,
                                    array("post_purchase", "=", 2, 0),
                                    array("post_purchase", $value),
                                    null,
                                )
                            );
                            $object->cards->{$key} = get_sql_query(
                                $product_cards_table,
                                array("image", "name", "url", "requires_account"),
                                array(
                                    array("product_id", $productID),
                                    array("deletion_date", null),
                                    null,
                                    array("post_purchase", "=", 2, 0),
                                    array("post_purchase", $value),
                                    null,
                                )
                            );
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
                        $object->downloads = get_sql_query(
                            $product_updates_table,
                            array(
                                "file_name",
                                "file_rename",
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

                                if (!array_key_exists($hash, $downloads)) {
                                    $downloads[$hash] = $value;
                                }

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
                                "accepted_account_product_id"
                            ),
                            array(
                                array("product_id", $productID),
                                array("deletion_date", null)
                            )
                        );

                        if (!empty($object->identification)) {
                            $identifications = array();

                            foreach ($object->identification as $identification) {
                                $identifications[$identification->accepted_account_id] = $identification->accepted_account_product_id;
                            }
                            $object->identification = $identifications;
                        }
                        if (!$hasProduct) {
                            $cacheKeyCopy = $cacheKey;
                            $cacheKeyCopy[] = $productID;
                            set_key_value_pair($cacheKeyCopy, array($object), "1 minute"); // Update individual cache conveniently
                        }
                    }
                }
            }
            set_key_value_pair($cacheKey, $array, $sql_max_cache_time);
        }
        $isEmpty = empty($array);
        return new MethodReply(!$isEmpty, $isEmpty ? "Product not found." : null, $array);
    }

    public function create()
    {
        //todo
    }

    public function delete()
    {
        //todo
    }
}
