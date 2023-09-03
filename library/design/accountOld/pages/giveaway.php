<?php

function loadGiveaway()
{
    $productGiveaway = new ProductGiveaway(null);
    $currentGiveaway = $productGiveaway->getCurrent();

    if ($currentGiveaway->isPositiveOutcome()) { // Check if current giveaway exists
        $currentGiveaway = $currentGiveaway->getObject();
        $productToWin = $currentGiveaway->product;

        if ($productToWin != null) { // Check if product of the current giveaway is valid
            $lastGiveawayInformation = $productGiveaway->getLast();

            if ($lastGiveawayInformation->isPositiveOutcome()) { // Check if the product of the last giveaway is valid
                global $website_url;
                echo "<div class='area'>";

                $lastGiveawayInformation = $lastGiveawayInformation->getObject();
                $lastGiveawayWinners = $lastGiveawayInformation[0];
                $days = max(get_date_days_difference($currentGiveaway->expiration_date), 1);

                // Optimisation
                $productToWinImage = $productToWin->image;
                $productToWinName = $productToWin->name;

                // Text
                $amount = $currentGiveaway->amount;
                $nextWinnersText = $amount > 1 ? $amount . " winners" : "the winner";

                echo "<div class='area_title'>$productToWinName<br><b>Giveaway</b></div>";
                $productURL = $productToWin->url;
                $alt = strtolower(strip_tags($productToWinName));
                echo "<div class='area_logo'><a href='$productURL'><img src='$productToWinImage' alt='$alt'></a></div>";

                if (!empty($lastGiveawayWinners)) { // Check if winners exist
                    $winnerAccounts = "";

                    foreach ($lastGiveawayWinners as $winner) {
                        $winnerAccounts .= $winner . ", ";
                    }
                    echo "<div class='area_text'>In the last giveaway, <b>" . substr($winnerAccounts, 0, -2) . "</b> won the product <b>" . $lastGiveawayInformation[1]->name . "</b>.
                          Next giveaway will end in <b>$days " . ($days == 1 ? "day" : "days") . "</b> and <b>$nextWinnersText</b> will receive <b>$productToWinName</b> for free</b>.";
                } else {
                    echo "<div class='area_text'>Next giveaway will end in <b>$days " . ($days == 1 ? "day" : "days") . "</b> and <b>$nextWinnersText</b> will
                          receive <b>$productToWinName</b> for free</b>.";
                }
                echo " <a href='$website_url/viewProduct/?id=18'>Click here to learn how to participate!</a></div>";
                echo "</div>";
            }
        }
    }
}
