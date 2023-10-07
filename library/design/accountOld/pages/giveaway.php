<?php


function load_account_giveaway(Account $account): void
{
    $productGiveaway = $account->getGiveaway();
    $currentGiveaway = $productGiveaway->getCurrent(null, 1, "14 days", true);

    if ($currentGiveaway->isPositiveOutcome()) { // Check if current giveaway exists
        $currentGiveaway = $currentGiveaway->getObject();
        $productToWin = $currentGiveaway->product;

        if ($productToWin != null) { // Check if product of the current giveaway is valid
            $lastGiveawayInformation = $productGiveaway->getLast();

            if ($lastGiveawayInformation->isPositiveOutcome()) { // Check if the product of the last giveaway is valid
                global $website_url;
                echo "<div class='area50'>";

                $lastGiveawayInformation = $lastGiveawayInformation->getObject();
                $lastGiveawayWinners = $lastGiveawayInformation[0];
                $days = max(get_date_days_difference($currentGiveaway->expiration_date), 1);

                // Optimisation
                $productToWinImage = $productToWin->image;
                $productToWinName = $productToWin->name;

                // Text
                $amount = $currentGiveaway->amount;
                $nextWinnersText = $amount > 1 ? $amount . " winners" : "the winner";

                echo "<div class='area_title'><b>GIVEAWAY</b></div>";
                $productURL = $productToWin->url;
                $alt = strtolower(strip_tags($productToWinName));
                $description = "<div class='area_text'>";

                if (!empty($lastGiveawayWinners)) { // Check if winners exist
                    $winnerAccounts = "";

                    foreach ($lastGiveawayWinners as $winner) {
                        $winnerAccounts .= $winner . ", ";
                    }
                    $description.= "<b>" . substr($winnerAccounts, 0, -2) . "</b> recently won the product <b>" . $lastGiveawayInformation[1]->name . "</b>. ";
                }
                $description .= "Next giveaway will end in <b>$days " . ($days == 1 ? "day" : "days") . "</b> and <b>$nextWinnersText</b> will receive <b>$productToWinName</b> for free</b>.";
                echo $description;
                echo "<br><a href='$website_url/viewProduct/?id=18'>Click here to learn how to participate!</a></div>";
                echo "<div class='area_logo'><a href='$productURL'><img src='$productToWinImage' alt='$alt'></a></div>";
                echo "</div>";
            }
        }
    }
}
