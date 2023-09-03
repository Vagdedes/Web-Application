<?php

function submit_GameCloud_FailedDiscordWebhook($version, $platform, $licenseID, $productID, $url, $details, $error)
{
    global $failed_discord_webhooks_table;
    sql_insert($failed_discord_webhooks_table,
        array(
            "creation_date" => get_current_date(),
            "version" => $version,
            "platform_id" => $platform,
            "license_id" => $licenseID,
            "product_id" => $productID,
            "webhook_Url" => $url,
            "details" => json_encode($details),
            "error" => $error
        )
    );
}

function canSend_GameCloud_DiscordWebhook($platform, $licenseID, $productID, $key = null, $futureTime = "1 second")
{
    global $discord_webhooks_table;
    $isKeyNull = $key === null;

    if (!$isKeyNull) {
        $key = string_to_integer($key);
    }
    $query = sql_query("SELECT id, expiration_time FROM $discord_webhooks_table WHERE"
        . " platform_id = '$platform' AND license_id = '$licenseID' AND product_id = '$productID'"
        . " AND key_hash " . ($isKeyNull ? "IS NULL" : "= '$key'") . " LIMIT 1;");
    $canSend = false;

    if ($query == null) {
        sql_insert($discord_webhooks_table,
            array(
                "platform_id" => $platform,
                "license_id" => $licenseID,
                "product_id" => $productID,
                "key_hash" => $key,
                "expiration_time" => get_future_date($futureTime)
            )
        );
        $canSend = true;
    } else {
        switch ($query->num_rows) {
            case 0:
                sql_insert(
                    $discord_webhooks_table,
                    array(
                        "platform_id" => $platform,
                        "license_id" => $licenseID,
                        "product_id" => $productID,
                        "key_hash" => $key,
                        "expiration_time" => get_future_date($futureTime)
                    )
                );
                $canSend = true;
                break;
            case 1:
                $row = $query->fetch_assoc();

                if (time() >= $row["expiration_time"]) {
                    sql_query("UPDATE $discord_webhooks_table SET expiration_time = '" . strtotime("+" . $futureTime) . "' WHERE id = '" . $row["id"] . "';");
                    $canSend = true;
                }
                break;
            default:
                break;
        }
    }

    if (!has_memory_cooldown($discord_webhooks_table, "15 minutes")) {
        delete_sql_query(
            $discord_webhooks_table,
            array(
                array("expiration_time", "<", time())
            )
        );
    }
    return $canSend;
}
