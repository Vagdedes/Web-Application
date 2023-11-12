<?php

class AccountOffer
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function find(int|string $offerID = null, bool $checkOwnership = true): MethodReply
    {
        $applicationID = $this->account->getDetail("application_id");
        $hasAccount = $this->account->exists();
        $hasOffer = !$hasAccount && $offerID !== null;

        if ($hasOffer) {
            $functionality = $this->account->getFunctionality()->getResult(AccountFunctionality::VIEW_OFFER);

            if (!$functionality->isPositiveOutcome()) {
                return new MethodReply(false, $functionality->getMessage());
            }
        }
        $validProducts = $this->account->getProduct()->find();

        if ($validProducts->isPositiveOutcome()) {
            global $product_offers_table;
            set_sql_cache();
            $offers = get_sql_query(
                $product_offers_table,
                null,
                array(
                    $hasAccount ? array("account_id", $this->account->getDetail("application_id")) : array("application_id", $applicationID),
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
                global $website_account_url, $product_offer_divisions_table;
                $validProducts = $validProducts->getObject();
                $accountExists = $this->account->exists();
                $purchases = $accountExists ? $this->account->getPurchases()->getCurrent() : array();

                foreach ($offers as $offer) {
                    if ($accountExists || $offer->requires_account === null) {
                        if ($accountExists
                            && $checkOwnership
                            && $offer->required_products !== null) {
                            foreach (explode("|", $offer->required_products) as $requiredProduct) {
                                if (!$this->account->getPurchases()->owns($requiredProduct)->isPositiveOutcome()) {
                                    continue 2;
                                }
                            }
                        }
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
                        $offer->url = $website_account_url . "/viewOffer/?id=" . $offer->id;

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
                                                return new MethodReply(false);
                                            } else {
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        return new MethodReply(true, null, $offer);
                    }
                }
            }
        }
        return new MethodReply(false);
    }
}