<?php

function loadMain(Account $account, $isLoggedIn)
{
    global $website_url;

    echo "<div class='area'><div class='area_text'>";
    echo "Elevate your Minecraft servers with top-tier plugins and the game-changing Spartan AntiCheat. Connect with us below for unbeatable protection against hack modules.";
    echo "<p><iframe src='https://discordapp.com/widget?id=289384242075533313&theme=dark' width='350' height='500' allowtransparency='false' frameborder='0'></iframe>";
    echo "</div>";
    echo "<div class='area_form' id='marginless'>
                    <a href='$website_url/help' class='button' id='blue'>Get Help</a>
                </div>";
    echo "</div>";

    // Separator

    echo "<div class='area' id='darker'>";
    $validProducts = $account->getProduct()->find(null, false);

    if ($validProducts->isPositiveOutcome()) {
        echo "<div class='product_list'><ul>";

        foreach ($validProducts->getObject() as $product) {
            $image = $product->image;

            if ($image !== null
                && $product->show_in_list !== null) {
                echo "<li><a href='{$product->url}'><div class='product_list_contents' style='background-image: url($image);'>";
                echo "<div class='product_list_title'>{$product->name}</div>";
                echo "<span>" . account_product_prompt($account, $isLoggedIn, $product) . "</span>";
                echo "</div></a></li>";
            }
        }
        echo "</ul></div>";
    } else {
        echo "<div class='area_text'>No products are currently available.</div><p>";
    }
    if ($isLoggedIn) {
        echo "<div class='area_form' id='marginless'>
                    <a href='$website_url/profile' class='button' id='green'>My Account</a>
                </div>";
    } else {
        echo "<div class='area_form' id='marginless'>
                    <a href='$website_url/profile' class='button' id='green'>Create Your Account Today</a>
                    <p>
                    <a href='$website_url/profile' class='button' id='blue'>Click Here to Log In</a>
                </div>";
    }
    echo "</div>";

    // Separator

    $offer = $account->getOffer()->find();

    if ($offer->isPositiveOutcome()) {
        $offer = $offer->getObject();
        echo "<div class='area' id='darker'>";

        foreach ($offer->divisions as $divisions) {
            foreach ($divisions as $division) {
                echo $division->description;
            }
        }
        echo "</div>";
    }

    // Separator

    loadGiveaway($account);
}

?>