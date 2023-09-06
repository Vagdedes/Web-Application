<?php

function getActiveCustomers($productID, $limit = 0)
{
    $cacheKey = array(
        $productID,
        $limit,
        "active-customers"
    );
    $finalObject = new stdClass();
    $finalObject->amount = 0;
    $cache = get_key_value_pair($cacheKey);

    if (is_object($cache)) {
        return $cache;
    }

    $hasLimit = is_numeric($limit) && $limit > 0;
    global $verifications_table, $account_purchases_table, $product_purchases_table;
    $validProducts = getValidProducts1();

    if ($hasLimit) {
        $limit = abs($limit);
    }
    $nameAliasArray = array();
    $licenseArray = array();

    foreach ($validProducts as $validProductName => $validProductObject) {
        if ($productID == $validProductObject->id || $productID == $validProductObject->alias_id) {
            $nameAliasArray[] = $validProductName;
        }
    }

    // Cloud Database

    for ($i = 0; $i < 2; $i++) {
        $query = sql_query(
            $i == 0 ? "SELECT license_id, platform_id FROM $verifications_table WHERE product_id = '$productID' AND access_failure IS NULL AND dismiss IS NULL;"
                : "SELECT license_id, platform_id FROM $account_purchases_table WHERE product_id = '$productID' AND deletion_date IS NULL;"
        );

        if (isset($query->num_rows) && $query->num_rows > 0) {
            while ($row = $query->fetch_assoc()) {
                $rowKey = $row["license_id"] . "-" . $row["platform_id"];

                if (!in_array($rowKey, $licenseArray)) {
                    $licenseArray[] = $rowKey;

                    if ($hasLimit && sizeof($licenseArray) == $limit) {
                        break;
                    }
                }
            }
        }
    }

    // Account Database

    $query = sql_query("SELECT account_id FROM $product_purchases_table WHERE product_id = '$productID' AND deletion_date IS NULL;");

    if (isset($query->num_rows) && $query->num_rows > 0) {
        global $platformsTable;

        while ($row = $query->fetch_assoc()) {
            $accountID = $row["account_id"];
            $childQuery = sql_query("SELECT platform_id, accepted_account_id FROM $platformsTable WHERE account_id = '$accountID' AND deletion_date IS NULL LIMIT 1;");

            if (isset($childQuery->num_rows) && $childQuery->num_rows > 0) {
                $breakLoop = false;

                while ($childRow = $childQuery->fetch_assoc()) {
                    $rowKey = $childRow["platform_id"] . "-" . $childRow["accepted_account_id"];

                    if (!in_array($rowKey, $licenseArray)) {
                        $licenseArray[] = $rowKey;

                        if ($hasLimit && sizeof($licenseArray) == $limit) {
                            $breakLoop = true;
                            break;
                        }
                    }
                }

                if ($breakLoop) {
                    break;
                }
            }
        }
    }

    // SpigotMC Database

    $spigotMCDownloads = 0;
    ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36');
    $contents = @file_get_contents("https://api.spigotmc.org/simple/0.1/index.php?action=getResourcesByAuthor&id=66556");

    if ($contents !== false) {
        $json = json_decode($contents);

        if (is_array($json)) {
            foreach ($json as $plugin) {
                if (isset($plugin->title) && isset($plugin->stats->downloads)) {
                    $title = str_replace(" ", "", $plugin->title);

                    foreach ($nameAliasArray as $nameAlias) {
                        if (strpos($title, $nameAlias) !== false) {
                            $spigotMCDownloads = min($plugin->stats->downloads, $limit);
                            break;
                        }
                    }
                }
            }
        }
    }

    // Results

    $finalObject->amount = min(max(sizeof($licenseArray), $spigotMCDownloads), $limit);
    set_key_value_pair($cacheKey, $finalObject, "");
    return $finalObject;
}
