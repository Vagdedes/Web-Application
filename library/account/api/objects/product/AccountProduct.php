<?php

class AccountProduct
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function find(int|string $productID = null,
                         bool       $documentation = true,
                         bool       $calculateBuyers = false,
                         int|string $accountID = null): MethodReply
    {
        $applicationID = $this->account->getDetail("application_id");
        $hasAccountPointer = $accountID !== null;
        $hasProduct = !$hasAccountPointer && $productID !== null;

        if ($hasProduct) {
            $functionality = $this->account->getFunctionality();
            $functionalityOutcome = $functionality->getResult(AccountFunctionality::VIEW_PRODUCT);

            if (!$functionalityOutcome->isPositiveOutcome()) {
                return new MethodReply(false, $functionalityOutcome->getMessage());
            }
        }
        $accountExists = $this->account->exists();
        $array = get_sql_query(
            AccountVariables::PRODUCTS_TABLE,
            null,
            array(
                $hasAccountPointer ? array("account_id", $accountID) : array("application_id", $applicationID),
                array("deletion_date", null),
                $hasProduct ? array("id", $productID) : ""
            ),
            $hasProduct ? null
                : array(
                "DESC",
                "priority"
            )
        );
        $isEmpty = empty($array);

        if (!$isEmpty) {
            foreach ($array as $arrayKey => $object) {
                if ($accountExists || $object->requires_account === null) {
                    $uniquePatreonTiers = array();
                    $productID = $object->id;
                    $object->transaction_search = get_sql_query(
                        AccountVariables::PRODUCT_TRANSACTION_SEARCH_TABLE,
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
                            "tier_id",
                            "individual",
                            "min_executions",
                            "max_executions"
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
                    $object->tiers = new stdClass();
                    $object->tiers->free = array();
                    $object->tiers->paid = array();
                    $object->tiers->all = get_sql_query(
                        AccountVariables::PRODUCT_TIERS_TABLE,
                        array(
                            "id",
                            "name",
                            "price",
                            "currency",
                            "required_patreon_tiers",
                            "required_patreon_cents",
                            "required_permission",
                            "give_permission",
                            "required_products"
                        ),
                        array(
                            array("product_id", $productID),
                            array("deletion_date", null),
                        ),
                        "give_permission DESC, price ASC"
                    );
                    $object->is_free = true;

                    if (!empty($object->tiers->all)) {
                        foreach ($object->tiers->all as $childArrayKey => $tier) {
                            unset($object->tiers->all[$childArrayKey]);

                            if ($tier->required_permission === null) {
                                $tier->required_permission = array();
                            } else {
                                $tier->required_permission = explode("|", $tier->required_permission);
                            }
                            if ($tier->required_products === null) {
                                $tier->required_products = array();
                            } else {
                                $tier->required_products = explode("|", $tier->required_products);
                            }
                            if ($tier->give_permission === null) {
                                $tier->give_permission = array();
                            } else {
                                $tier->give_permission = explode("|", $tier->give_permission);
                            }
                            if ($tier->required_patreon_tiers === null) {
                                $tier->required_patreon_tiers = array();
                            } else {
                                $tier->required_patreon_tiers = explode("|", $tier->required_patreon_tiers);

                                if (!empty($tier->required_patreon_tiers)) {
                                    foreach ($tier->required_patreon_tiers as $patreonTier) {
                                        if (!in_array($patreonTier, $uniquePatreonTiers)) {
                                            $uniquePatreonTiers[] = $patreonTier;
                                        }
                                    }
                                }
                            }
                            if ($tier->price === null) {
                                $object->tiers->free[$tier->id] = $tier;
                            } else {
                                $object->is_free = false;
                                $object->tiers->paid[$tier->id] = $tier;
                            }
                            $object->tiers->all[$tier->id] = $tier;
                        }
                    }
                    if ($object->is_free || !$calculateBuyers) {
                        $object->registered_buyers = 0;
                    } else {
                        $object->registered_buyers = sizeof(get_sql_query(
                            AccountVariables::PRODUCT_PURCHASES_TABLE,
                            array("id"),
                            array(
                                array("product_id", $productID),
                                array("deletion_date", null)
                            )
                        ));
                        if (!empty($uniquePatreonTiers)) {
                            $object->registered_buyers += sizeof(get_patreon2_subscriptions(null, $uniquePatreonTiers));
                        }
                    }
                    $object->compatibilities = get_sql_query(
                        AccountVariables::PRODUCT_COMPATIBILITIES_TABLE,
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
                        AccountVariables::PRODUCT_UPDATES_TABLE,
                        array(
                            "file_name",
                            "file_rename",
                            "file_type",
                            "required_permission",
                            "version",
                            "prefix",
                            "suffix",
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
                        "creation_date DESC, required_permission DESC",
                        10
                    );
                    $object->download_note = null;

                    if (empty($object->downloads)) {
                        $object->download_placeholder = null;
                        $object->latest_version = null;
                        $object->minimum_supported_version = null;
                        $object->supported_versions = array();
                    } else {
                        $downloads = array();
                        $object->supported_versions = array();

                        foreach ($object->downloads as $value) {
                            if ($value->required_permission !== null) {
                                $value->required_permission = explode("|", $value->required_permission);
                            }
                            $hash = string_to_integer($value->file_name);
                            $hash = overflow_integer(($hash * 31) + string_to_integer($value->file_type));
                            $hash = overflow_integer(($hash * 31) + string_to_integer($value->required_permission));

                            if (!array_key_exists($hash, $downloads)) {
                                $downloads[$hash] = $value;
                            }

                            if (!in_array($value->version, $object->supported_versions)) {
                                if ($value->version === null) {
                                    $object->supported_versions[] = $value;
                                } else {
                                    $object->supported_versions[$value->version] = $value;
                                }
                            }
                            if ($object->download_note === null) {
                                $object->download_note = $value->note;
                            }
                        }
                        $downloads = array_values($downloads);
                        $object->downloads = $downloads;
                        $object->latest_version = $downloads[0];
                        $object->minimum_supported_version = $downloads[sizeof($downloads) - 1]->version;
                    }
                } else {
                    unset($array[$arrayKey]);
                }
            }
        }
        return new MethodReply(!$isEmpty, $isEmpty ? "Product not found." : null, $array);
    }

}
