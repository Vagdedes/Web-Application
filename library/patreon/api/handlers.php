<?php

function get_patreon1_subscriptions($ignoreTiers = null, $targetTiers = null): array
{
    $hasIgnoreTiers = $ignoreTiers !== null;
    $hasTargetTiers = $ignoreTiers !== null;
    $cacheKey = array(
        $ignoreTiers,
        $targetTiers,
        "patreon-1-subscriptions"
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_array($cache)) {
        return $cache;
    }
    $key = get_keys_from_file("/var/www/.structure/private/patreon_1_credentials", 1);

    if ($key !== null) {
        global $patreon_campaign_id;
        $key = $key[0];
        $results = array();
        $link = "https://www.patreon.com/api/oauth2/api/campaigns/" . $patreon_campaign_id . "/pledges";

        while ($link !== null) {
            $reply = json_decode(get_curl(
                $link,
                "GET",
                array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $key
                ),
                null,
                3
            ));

            if (isset($reply->data) && isset($reply->included)) {
                $userIDs = array();

                foreach ($reply->data as $data) {
                    if (isset($data->relationships->reward->data->id)
                        && (!$hasIgnoreTiers || !in_array($data->relationships->reward->data->id, $ignoreTiers))
                        && (!$hasTargetTiers || in_array($data->relationships->reward->data->id, $targetTiers))) {
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

function get_patreon2_subscriptions($ignoreTiers = null, $targetTiers = null): array
{
    $hasIgnoreTiers = $ignoreTiers !== null;
    $hasTargetTiers = $ignoreTiers !== null;
    $cacheKey = array(
        $ignoreTiers,
        $targetTiers,
        "patreon-2-subscriptions"
    );
    $cache = get_key_value_pair($cacheKey);

    if (is_array($cache)) {
        return $cache;
    }
    $key = get_keys_from_file("/var/www/.structure/private/patreon_2_credentials", 1);

    if ($key !== null) {
        global $patreon_campaign_id;
        $key = $key[0];
        $results = array();
        $arguments = "currently_entitled_tiers,address";
        $arguments .= "&fields[member]=full_name,last_charge_date,next_charge_date,last_charge_status,lifetime_support_cents,currently_entitled_amount_cents,patron_status";
        $arguments .= "&fields[tier]=amount_cents,created_at,description,discord_role_ids,edited_at,patron_count,published,published_at,requires_shipping,title,url";
        $arguments .= "&fields[address]=addressee,city,line_1,line_2,phone_number,postal_code,state";
        $link = "https://www.patreon.com/api/oauth2/v2/campaigns/" . $patreon_campaign_id . "/members?include="
            . str_replace("[", "%5B", str_replace("]", "%5D", $arguments));

        while ($link !== null) {
            $reply = json_decode(get_curl(
                $link,
                "GET",
                array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $key
                ),
                null,
                3
            ));

            if (isset($reply->data)) {
                foreach ($reply->data as $patron) {
                    if (isset($patron->type)
                        && $patron->type == "member"
                        && isset($patron->attributes->patron_status)
                        && $patron->attributes->patron_status == "active_patron"
                        && isset($patron->relationships->currently_entitled_tiers->data)) {
                        if ($hasIgnoreTiers || $hasTargetTiers) {
                            foreach ($patron->relationships->currently_entitled_tiers->data as $tier) {
                                if (isset($data->relationships->reward->data->id)
                                    && (!$hasIgnoreTiers || !in_array($tier->id, $ignoreTiers))
                                    && (!$hasTargetTiers || in_array($tier->id, $targetTiers))) {
                                    $results[] = $patron;
                                }
                            }
                        } else {
                            $results[] = $patron;
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