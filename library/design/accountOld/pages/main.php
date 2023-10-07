<?php


function load_account_main_page(Account $account, $isLoggedIn): void
{
    $discord_url = "https://" . get_domain() . "/discord";

    echo "<div class='areas'>";
    echo "<div class='area50'><div class='area_title'>CONNECT WITH US</div><div class='area_text'>";
    echo "Elevate your Minecraft servers with top-tier plugins and the game-changing Spartan AntiCheat. Connect with us below for unbeatable protection against hack modules.";

    $height = 75;
    echo "<p><div style='position:relative;'>";
    echo "<iframe src='https://discordapp.com/widget?id=289384242075533313&theme=dark' width='100%' height='$height' allowtransparency='false' frameborder='0'></iframe>";
    echo "<a href='$discord_url' style='position: absolute; top: 0; left: 0; display: inline-block; width: 100%; height: {$height}px; z-index: 5;'></a>";
    echo "</div>";
    echo "</div>";
    echo "</div>";

    // Separator

    load_account_giveaway($account);
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
}

?>