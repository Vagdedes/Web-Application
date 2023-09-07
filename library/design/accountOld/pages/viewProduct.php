<?php


function loadViewProduct(Account $account, $isLoggedIn)
{
    $productArguments = explode(".", get_form_get("id"));
    $argumentSize = sizeof($productArguments);
    $productID = $productArguments[$argumentSize - 1];

    if (is_numeric($productID) && $productID > 0) {
        global $website_url;
        $productFound = $account->getProduct()->find($productID);

        if ($productFound->isPositiveOutcome()) {
            $productFound = $productFound->getObject()[0];
            $name = $productFound->name;
            $nameURL = str_replace(" ", "-", $name);

            if ($argumentSize == 1 || $productArguments[0] !== $nameURL) {
                redirect_to_url($website_url . "/viewProduct/?id=$nameURL.$productID", array("id"));
                return;
            }
            $description = $productFound->description;
            $image = $productFound->image;
            $legal = $productFound->legal_information;
            $isFree = $productFound->price === null;
            $developmentDays = get_date_days_difference($productFound->creation_date);
            $hasPurchased = $productFound->price === null
                || $isLoggedIn && $account->getPurchases()->owns($productID)->isPositiveOutcome();

            // Separator

            if ($isFree) {
                $activeCustomers = "";
                $price = "";
            } else {
                if ($productFound->registered_buyers > 0) {
                    $activeCustomers = "<li style='width: auto;'>$productFound->registered_buyers Customers</li>";
                } else {
                    $activeCustomers = "";
                }
                $price = "<li style='width: auto;'>" . $productFound->price . " " . $productFound->currency . "</li>";
            }

            echo "<div class='area'>";

            if ($image !== null) {
                $alt = strtolower($name);
                echo "<div class='area_logo'><img src='$image' alt='$alt'></div>";
            }
            $release = $productFound->latest_version !== null ? "<li style='width: auto;'>Release {$productFound->latest_version}</li>" : "";
            echo "<div class='area_title'>$name</div>
                    <div class='area_text'>$description</div>";

            echo "<div class='area_list' id='text'>
                    <ul>
                        <li style='width: auto;'>$developmentDays Days Of Development</li>
                        $release
                        $price
                        $activeCustomers
                    </ul>
                 </div><p>
                </div>";

            // Separator
            $css = "";
            $overviewContents = "";
            $productDivisions = $productFound->divisions;

            if (!empty($productDivisions)) {
                foreach ($productDivisions as $family => $divisions) {
                    if (!empty($family)) {
                        $overviewContents .= "<div class='area_title'>$family</div>";
                    }
                    $overviewContents .= "<div class='area_list'><ul>";

                    foreach ($divisions as $division) {
                        $contents = $division->description;

                        if ($division->no_html != null) {
                            $contents = htmlspecialchars($contents);
                        }
                        $contents = str_replace("\n", "<br>", $contents);
                        $overviewContents .= "<li>
                                <div class='area_list_title'>{$division->name}</div>
                                <div class='area_list_contents'>$contents</div>
                                </li>";
                    }
                    $overviewContents .= "</ul></div><br><br>";
                }
            }

            // Separator
            $offer = $productFound->show_offer;

            if ($offer === null) {
                $productCompatibilities = $productFound->compatibilities;

                if (!empty($productCompatibilities)) {
                    $validProducts = $account->getProduct()->find();
                    $validProducts = $validProducts->getObject();

                    if (sizeof($validProducts) > 1) { // One because we already are quering one
                        $overviewContents .= "<div class='area_title'>Works With</div><div class='product_list'><ul>";

                        foreach ($productCompatibilities as $compatibility) {
                            $productObject = find_object_from_key_match($validProducts, "id", $compatibility);

                            if (is_object($productObject)) {
                                $compatibleProductImage = $productObject->image;

                                if ($compatibleProductImage != null) {
                                    $compatibleProductName = $productObject->name;
                                    $span = "<span>" . account_product_prompt($account, $isLoggedIn, $productObject) . "</span>";
                                    $productURL = $productObject->url;
                                    $overviewContents .= "<li><a href='$productURL'>
                                                        <div class='product_list_contents' style='background-image: url(\"$compatibleProductImage\");'>
                                                            <div class='product_list_title'>$compatibleProductName</div>
                                                            $span
                                                        </div>
                                                    </a>
                                                </li>";
                                }
                            }
                        }
                    }
                    $overviewContents .= "</ul></div>";
                }
            } else {
                $offer = $account->getOffer()->find($offer == -1 ? null : $offer);

                if ($offer->isPositiveOutcome()) {
                    $offer = $offer->getObject();

                    foreach ($offer->divisions as $divisions) {
                        foreach ($divisions as $division) {
                            $overviewContents .= $division->description;
                        }
                    }
                }
            }

            if (isset($overviewContents[0])) {
                echo "<div class='area' id='darker'>$overviewContents</div>";
            } else {
                $css = "darker";
            }

            // Separator

            $showLegal = true;
            $buttonInformation = "";
            $productButton = $hasPurchased ? null : $productFound->buttons->pre_purchase;

            if ($productButton !== null && sizeof($productButton) > 0) {
                $buttonInformation .= "<div class='area_text'>Your purchase will appear within minutes of completion.</div><p>";

                foreach ($productButton as $button) {
                    if ($isLoggedIn || $button->requires_account == null) {
                        $color = $button->color;
                        $buttonName = $button->name;
                        $url = $button->url;
                        $buttonInformation .= "<a href='$url' class='button' id='$color'>$buttonName</a> ";
                    }
                }
            } else if ($hasPurchased) {
                if (!empty($productFound->downloads)) {
                    if ($isLoggedIn) {
                        $productButton = $productFound->buttons->post_purchase;
                        $downloadNote = $productFound->download_note !== null ? "<div class='area_text'><b>IMPORTANT NOTE</b><br>" . $productFound->download_note . "</div>" : "";
                        $buttonInformation .= "$downloadNote<div class='area_form'><a href='$website_url/downloadFile/?id=$productID' class='button' id='blue'>Download $name</a>";

                        if (sizeof($productButton) > 0) {
                            foreach ($productButton as $button) {
                                $color = $button->color;
                                $buttonName = $button->name;
                                $url = $button->url;
                                $buttonInformation .= "<p><a href='$url' class='button' id='$color'>$buttonName</a>";
                            }
                        }
                        $buttonInformation .= "</div>";
                    } else {
                        $buttonInformation .= "<div class='area_form' id='marginless'>
                                        <a href='$website_url/profile' class='button' id='blue'>Log In To Download</a>
                                    </div>";
                    }
                } else {
                    if ($isLoggedIn) {
                        $showLegal = false;
                        /*echo "<div class='area_form' id='marginless'>
                                    <a href='#' class='button' id='red'>$name Is Not Downloadable</a>
                                </div>";*/
                    } else {
                        $showLegal = false;
                        $buttonInformation .= "<div class='area_form' id='marginless'>
                                        <a href='$website_url/profile' class='button' id='blue'>Log In To Learn More</a>
                                    </div>";
                    }
                }
            } else if (!$isFree) {
                if ($isLoggedIn) {
                    $buttonInformation .= "<div class='area_form' id='marginless'>
                                        <a href='#' class='button' id='red'>Not For Sale</a>
                                    </div>";
                } else {
                    $showLegal = false;
                    $buttonInformation .= "<div class='area_form' id='marginless'>
                                        <a href='$website_url/profile' class='button' id='blue'>Log In To Learn More</a>
                                    </div>";
                }
            }
            if (isset($buttonInformation[0])) {
                echo "<div class='area' id='$css'>";
                echo $buttonInformation;

                if ($showLegal && $legal !== null) {
                    echo "<p><div class='area_text'>By purchasing/downloading, you acknowledge and accept this product/service's <a href='$legal'>legal information</a>.</div>";
                }
                echo "</div>";
            }
        } else {
            $name = "Error";

            if ($argumentSize == 1 || $productArguments[0] !== $name) {
                redirect_to_url($website_url . "/viewProduct/?id=$name.$productID", array("id"));
                return;
            }
            load_account_page_message(
                "Website Error",
                "This product does not exist or is not currently available."
            );
        }
    } else {
        load_account_page_message(
            "Website Error",
            "This product does not exist or is not currently available."
        );
    }
}
