<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Spartan | Advanced AntiCheat | Hack Blocker</title>
    <meta name="description"
          content="Introducing Spartan AntiCheat, an advanced Minecraft anti-cheat meant to protect players from hack modules while analysing stored behavior to learn & adapt.">
    <link rel="shortcut icon" type="image/png" href="https://vagdedes.com/.images/bedrockIcon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"
          href='https://vagdedes.com/.css/universal.css?id="<?php echo rand(0, 2147483647) ?>'>
</head>
<body>

<?php
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/public/html/gameCloud/api/handlers/productStatistics.php';
$message = get_form_get("message");

if (strlen($message) > 0) {
    $message = substr($message, 0, 256);
    echo "<div class='message'>$message</div>";
}
?>

<div class="area">
    <div class="area_logo">
        <img src="../../../../vagdedes/.images/spartan.png" alt="spartan anticheat">
    </div>
    <div class="area_title">
        <?php
        $productStatistics = getProductStatistics($spartan_anticheat_product_id);
        ?>
        Spartan AntiCheat
    </div>
    <div class="area_text">
        Select your platform of preference to learn more about this product.
        <p>
    </div>
    <?php
    $showChoices = get_form_get("source") != "spigotmc";

    if ($showChoices) {
        echo "<a href='https://vagdedes.com/account/viewProduct/?id=1' class='button' id='green'>Vagdedes Services (Official)</a> ";
    }
    echo "<a href='https://www.spigotmc.org/resources/25638/' class='button' id='red'>SpigotMC</a> ";

    if ($showChoices) {
        echo "<a href='https://builtbybit.com/resources/11196/' class='button' id='blue'>BuiltByBit</a> ";
        echo "<a href='https://polymart.org/resource/350/' class='button' id='gray'>Polymart</a>";
    }

    if (is_object($productStatistics)) {
        ?>
        <div class="area_list" id="legal">
            <ul>
                <li>
                    <div class="area_list_title">Market Share</div>
                    <div class="area_list_contents">
                        Spartan owns
                        approximately <?php echo(is_numeric($productStatistics->reputation->market_share) ? ceil($productStatistics->reputation->market_share) : "(Error)"); ?>
                        % of the SpigotMC premium anti-cheat market, which is more than any competitor.
                    </div>
                </li>
                <li>
                    <div class="area_list_title">Concurrent Servers</div>
                    <div class="area_list_contents">
                        Spartan has been used in over <?php echo $productStatistics->server->count; ?> servers. That's
                        significantly more than most free & paid anti-cheats available.
                    </div>
                </li>
                <li>
                    <div class="area_list_title">Rating Accomplishments</div>
                    <div class="area_list_contents">
                        Spartan has <?php echo $productStatistics->reputation->unique_reviews; ?> unique reviews,
                        averaging at <?php echo cut_decimal($productStatistics->reputation->rating, 2); ?> out of 5
                        stars, which shows its quality & consistency over the years.
                    </div>
                </li>
            </ul>
        </div>

    <?php } ?>
</div>

<div class="area" id="darker">
    <div class="area_logo">
        <div class="paper">
            <ul>
                <li class="paper_top"></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
            </ul>
        </div>
    </div>

    <?php
    if (!is_object($productStatistics)) {
        ?>

        <div class="area_title">
            Statistics Temporarily Offline
        </div>

    <?php } else { ?>

        <div class="area_title">
            <b><?php echo $productStatistics->server->count; ?></b> Past & Present Servers Running Spartan
        </div>
        <div class="area_list">
            <ul>
                <?php
                echo "<li><div class='area_list_title'>Server</div><div class='area_list_contents'>"
                    . "<b>Minecraft Versions Used</b> " . $productStatistics->server->versions_count
                    . "<br><b>Average Server Description</b> " . $productStatistics->server->average_server_description
                    . "<br><b>Average Plugins</b> " . round($productStatistics->server->average_plugins)
                    . "<br><b>Maximum Plugins</b> " . $productStatistics->server->max_plugins
                    . "<br><b>Minimum Plugins</b> " . $productStatistics->server->min_plugins
                    . "</div></li>";

                echo "<li><div class='area_list_title'>Specifications</div><div class='area_list_contents'>"
                    . "<b>Average CPU Cores</b> " . round($productStatistics->specs->cpu_cores_average)
                    . "<br><b>Maximum CPU Cores</b> " . $productStatistics->specs->max_cpu_cores
                    . "<br><b>Minimum CPU Cores</b> " . $productStatistics->specs->min_cpu_cores
                    . "<br><b>Average RAM Megabytes</b> " . round($productStatistics->specs->ram_mb_average)
                    . "<br><b>Maximum RAM Megabytes</b> " . $productStatistics->specs->max_ram_mb
                    . "<br><b>Minimum RAM Megabytes</b> " . $productStatistics->specs->min_ram_mb
                    . "</div></li>";

                echo "<li><div class='area_list_title'>Network</div><div class='area_list_contents'>"
                    . "<b>Total IP Addresses</b> " . $productStatistics->network->total_ip_addresses
                    . "<b><br>Used Server Ports</b> " . $productStatistics->network->used_ports
                    . "<br><b>Most Used Server Port</b> " . $productStatistics->network->most_used_port
                    . "<br><b>Least Used Server Port</b> " . $productStatistics->network->least_used_port
                    . "</div></li>";

                echo "<li><div class='area_list_title'>Versions</div><div class='area_list_contents'>";
                $count = 0;

                foreach ($productStatistics->server->versions_by_percentage as $version => $percentage) {
                    $count++;
                    echo "<b>$version</b> $percentage%" . ($count % 3 == 0 ? "<br>" : "&nbsp;&nbsp;&nbsp;");
                }
                echo "</div></li>";
                ?>
            </ul>
        </div>

    <?php } ?>
</div>
<?php include("/var/www/.structure/design/footer/footer.php"); ?>
</body>
</html>