<?php

class ProductOffer
{
    private ?object $object;

    public function __construct($applicationID, ?Account $account, $offerID, $checkOwnership)
    {
        $functionality = new WebsiteFunctionality(
            $applicationID,
            WebsiteFunctionality::VIEW_OFFER,
            $account
        );
        $functionalityOutcome = $functionality->getResult();

        if (!$functionalityOutcome->isPositiveOutcome()) {
            $this->object = null;
        } else {
            $this->object = null;
            $validProducts = new WebsiteProduct($applicationID);

            if ($validProducts->hasResults()) {
                global $product_offers_table;
                $hasOffer = $offerID !== null;
                set_sql_cache("1 minute");
                $offers = get_sql_query(
                    $product_offers_table,
                    null,
                    array(
                        array("application_id", $applicationID),
                        array("deletion_date", null),
                        $hasOffer ? array("id", $offerID) : ""
                    ),
                    $hasOffer ? null
                        : array(
                        "DESC",
                        "priority"
                    ),
                    $hasOffer ? 1 : 0
                );

                if (!empty($offers)) {
                    global $website_url, $product_offer_divisions_table;
                    $validProducts = $validProducts->getResults();
                    $isObject = is_object($account);
                    $purchases = $isObject ? $account->getPurchases()->getCurrent() : array();

                    foreach ($offers as $offer) {
                        if ((!$isObject || !$checkOwnership || $offer->required_product === null || $account->getPurchases()->owns($offer->required_product))
                            && ($isObject || $offer->requires_account === null)) {
                            $query = get_sql_query(
                                $product_offer_divisions_table,
                                array("family", "name", "description", "no_html"),
                                array(
                                    array("offer_id", $offer->id),
                                    array("deletion_date", null)
                                )
                            );

                            if (!empty($query)) {
                                $divisions = array();

                                foreach ($query as $division) {
                                    foreach ($validProducts as $product) {
                                        $division->description = str_replace("%%__product_" . $product->id . "_name__%%", $product->name, $division->description);
                                        $division->description = str_replace("%%__product_" . $product->id . "_URL__%%", $product->url, $division->description);
                                        $division->description = str_replace("%%__product_" . $product->id . "_combined__%%", "<a href='{$product->url}'>{$product->name}</a>", $division->description);
                                    }

                                    if (array_key_exists($division->family, $divisions)) {
                                        $divisions[$division->family][] = $division;
                                    } else {
                                        $divisions[$division->family] = array($division);
                                    }
                                }
                                $offer->divisions = $divisions;
                            } else {
                                $offer->divisions = $query;
                            }
                            $offer->url = $website_url . "/viewOffer/?id=" . $offer->id;

                            if ($offer->included_products !== null) {
                                $offer->included_products = explode("|", $offer->included_products);
                            } else {
                                $offer->included_products = array();
                            }
                            if ($checkOwnership) {
                                $includedProductsAmount = sizeof($offer->included_products);

                                if ($includedProductsAmount > 0) {
                                    $ownedProducts = 0;

                                    foreach ($offer->included_products as $productID) {
                                        foreach ($purchases as $purchase) {
                                            if ($productID == $purchase->product_id) {
                                                $ownedProducts++;

                                                if ($ownedProducts == $includedProductsAmount) {
                                                    return;
                                                } else {
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $this->object = $offer;
                            break;
                        }
                    }
                }
            }
        }
    }

    public function getObject(): ?object
    {
        return $this->object;
    }

    public function found(): bool
    {
        return $this->object !== null;
    }
}