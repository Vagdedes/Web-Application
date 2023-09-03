<?php

function get_patreon_subscriptions($ignoreTiers = null): array
{
    $hasIgnoreTiers = $ignoreTiers !== null;
    $cacheKey = array(
        $hasIgnoreTiers ? serialize($ignoreTiers) : null,
        "patreon-subscriptions"
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_array($cache)) {
        return $cache;
    }
    $key = get_keys_from_file("/var/www/.structure/private/patreon_credentials", 1);

    if ($key !== null) {
        $key = $key[0];
        $results = array();
        $link = "https://www.patreon.com/api/oauth2/api/campaigns/3314007/pledges";

        while ($link !== null) {
            $reply = get_curl(
                $link,
                "GET",
                null,
                array(
                    "Authorization: Bearer " . $key
                )
            );

            if (isset($reply->data) && isset($reply->included)) {
                $userIDs = array();

                foreach ($reply->data as $data) {
                    if (isset($data->relationships->reward->data->id)
                        && !in_array($data->relationships->reward->data->id, $ignoreTiers)) {
                        $userIDs[] = $data->relationships->patron->data->id;
                    }
                }

                if (!empty($userIDs)) {
                    foreach ($reply->included as $data) {
                        if (isset($data->id)
                            && in_array($data->id, $userIDs)) {
                            $results[] = $data;
                        }
                    }
                }
            }
            $link = $reply->links->next ?? null;
        }
        set_key_value_pair($cacheKey, $results, "1 minute");
        return $results;
    } else {
        return array();
    }
}