<?php

function getProductStatistics($productID = null)
{
    $hasProductID = $productID != null;
    $cacheKey = array(
        $hasProductID ? $productID : 0,
        "product-statistics"
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_bool($cache) || is_object($cache)) {
        return $cache;
    }
    global $server_specifications_table;
    $enabled = true;
    $query = $enabled ? sql_query("SELECT version, ram, cpu, plugins, ip_address, port, motd FROM $server_specifications_table" . ($hasProductID ? " WHERE product_id = '$productID';" : ";")) : null;

    if ($query == null || $query->num_rows == 0) {
        set_key_value_pair($cacheKey, false, "");
        return false;
    }
    $object = new stdClass();
    $object->server = new stdClass();
    $object->server->count = 0;

    $ports = array();
    $ips = array();
    $motdCount = 0;
    $motdLength = 0;

    ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.163 Safari/537.36');
    $spigotMC_json = @file_get_contents("https://api.spigotmc.org/simple/0.2/index.php?action=getResource&id=25638");
    $spigotMC_json = $spigotMC_json === false ? null : json_decode($spigotMC_json);
    $hasStats = isset($spigotMC_json->stats);
    $hasDownloads = $hasStats && isset($spigotMC_json->stats->downloads);
    $hasRating = $hasStats && isset($spigotMC_json->stats->rating);
    $hasReviews = $hasRating && isset($spigotMC_json->stats->reviews);

    $object->server->average_plugins = 0;
    $object->server->max_plugins = 0;
    $object->server->min_plugins = 2147483647;
    $object->server->versions_by_percentage = array();
    $object->server->versions_count = 0;
    $object->server->average_server_description = 0;

    $object->network = new stdClass();
    $object->network->used_ports = 0;
    $object->network->servers_per_ip_address = 0;
    $object->network->total_ip_addresses = 0;
    $object->network->most_used_port = 0;
    $object->network->least_used_port = 0;

    $object->specs = new stdClass();
    $object->specs->cpu_cores_average = 0;
    $object->specs->max_cpu_cores = 0;
    $object->specs->min_cpu_cores = 2147483647;
    $object->specs->ram_mb_average = 0;
    $object->specs->max_ram_mb = 0;
    $object->specs->min_ram_mb = 2147483647;

    // Separator

    $object->reputation = new stdClass();
    $object->reputation->rating = $hasRating && isset($spigotMC_json->stats->rating) ? $spigotMC_json->stats->rating : 5;
    $object->reputation->unique_reviews = $hasReviews && isset($spigotMC_json->stats->reviews->unique) ? $spigotMC_json->stats->reviews->unique : "(Error)";
    $object->reputation->total_reviews = $hasReviews && isset($spigotMC_json->stats->reviews->total) ? $spigotMC_json->stats->reviews->total : "(Error)";
    $object->reputation->market_share = "(Error)";

    if ($hasDownloads) {
        $uniqueQuery = sql_query("SELECT resource_id FROM competition.spigotmc_premium_anticheats;");

        if (is_object($uniqueQuery) && $uniqueQuery != null && $uniqueQuery->num_rows > 0 && $spigotMC_json != null && is_object($spigotMC_json)) {
            $ownDownloads = isset($spigotMC_json->stats->downloads) ? $spigotMC_json->stats->downloads : 0;
            $allDownloads = 0;

            while ($row = $uniqueQuery->fetch_assoc()) {
                $resourceID = $row["resource_id"];

                if ($resourceID == 25638) {
                    $allDownloads += $ownDownloads;
                } else {
                    $contents = @file_get_contents("https://api.spigotmc.org/simple/0.1/index.php?action=getResource&id=$resourceID");

                    if ($contents !== false) {
                        $spigotMC_queryJson = json_decode($contents);

                        if (isset($spigotMC_queryJson->stats) && isset($spigotMC_queryJson->stats->downloads)) {
                            $allDownloads += $spigotMC_queryJson->stats->downloads;
                        } else {
                            $allDownloads = 0;
                            break;
                        }
                    }
                }
            }
            if ($allDownloads > 0) {
                $object->reputation->market_share = cut_decimal(($ownDownloads / $allDownloads) * 100.0, 5);
            }
        }
    }

    // Separator

    $versions = $object->server->versions_by_percentage;

    if ($query != null && $query->num_rows > 0) {
        while ($row = $query->fetch_assoc()) {
            $object->server->count++;

            // Separator
            $port = $row["port"];
            $ip = $row["ip_address"] . $port;
            $ips[] = $ip; // Do not check, each ip & port is already unique

            // Separator
            $pluginsRow = $row["plugins"];
            $object->server->average_plugins += $pluginsRow;
            $object->server->max_plugins = max($pluginsRow, $object->server->max_plugins);
            $object->server->min_plugins = min($pluginsRow, $object->server->min_plugins);

            // Separator
            $cpuRow = $row["cpu"];
            $object->specs->cpu_cores_average += $cpuRow;
            $object->specs->max_cpu_cores = max($cpuRow, $object->specs->max_cpu_cores);
            $object->specs->min_cpu_cores = min($cpuRow, $object->specs->min_cpu_cores);

            // Separator
            $ramRow = $row["ram"];
            $object->specs->ram_mb_average += $ramRow;
            $object->specs->max_ram_mb = max($ramRow, $object->specs->max_ram_mb);
            $object->specs->min_ram_mb = min($ramRow, $object->specs->min_ram_mb);

            // Separator
            if (array_key_exists($port, $ports)) {
                $ports[$port] += 1;
            } else {
                $ports[$port] = 1;
            }

            // Separator
            $version = $row["version"];

            if ($version !== null) {
                if (array_key_exists($version, $versions)) {
                    $versions[$version] += 1;
                } else {
                    $versions[$version] = 1;
                }
            }

            // Separator
            $motd = $row["motd"];

            if ($motd !== null) {
                $motdCount++;
                $motdLength += strlen($motd);
            }
        }
    }

    // Separator (Set before secondary counting loop due to missing statistics)
    $serverCount = $object->server->count;

    // Separator
    $totalIpAddresses = sizeof($ips);
    $object->server->count = $totalIpAddresses;
    $object->server->average_server_description = $motdCount > 0 ? round($motdLength / $motdCount) : 0;

    if ($enabled) {
        $totalPorts = 0;
        $maxUsers = 0;
        $minUsers = 2147483647;

        foreach ($ports as $port => $users) {
            $totalPorts += $users;

            if ($users > $maxUsers) {
                $maxUsers = $users;
                $object->network->most_used_port = $port;
            }
            if ($users < $minUsers) {
                $minUsers = $users;
                $object->network->least_used_port = $port;
            }
        }

        // Separator

        foreach ($versions as $key => $value) {
            $versions[$key] = $serverCount == 0 ? 0 : cut_decimal(($value / $serverCount) * 100.0, 2);
        }

        // Separator

        $object->server->average_plugins = $serverCount == 0 ? 0 : cut_decimal($object->server->average_plugins / $serverCount, 5);
        $object->server->versions_by_percentage = $versions;
        $object->server->versions_count = sizeof($versions);

        $object->network->total_ip_addresses = $totalIpAddresses;
        $object->network->used_ports = sizeof($ports);

        $object->specs->cpu_cores_average = $serverCount == 0 ? 0 : cut_decimal($object->specs->cpu_cores_average / $serverCount, 5);
        $object->specs->ram_mb_average = $serverCount == 0 ? 0 : cut_decimal($object->specs->ram_mb_average / $serverCount, 5);
    }
    set_key_value_pair($cacheKey, $object, "");
    return $object;
}
