<?php

function clear_patreon_subscription_cache(): void
{
    clear_memory(
        array(
            "patreon-1-links",
            "patreon-2-links",
        ),
        true,
        0,
        function ($value) {
            return is_object($value);
        }
    );
    clear_memory(
        array(
            "patreon-1-subscriptions",
            "patreon-2-subscriptions"
        ),
        true,
        0,
        function ($value) {
            return is_array($value);
        }
    );
}

function get_patreon1_subscriptions(?array $ignoreTiers = null, ?array $targetTiers = null): array
{
    $hasIgnoreTiers = $ignoreTiers !== null;
    $hasTargetTiers = $ignoreTiers !== null;
    $totalCacheKey = array(
        $ignoreTiers,
        $targetTiers,
        "patreon-1-subscriptions"
    );
    $cache = get_key_value_pair($totalCacheKey);

    if (is_array($cache)) {
        return $cache;
    }
    $key = get_keys_from_file(PatreonVariables::CREDENTIALS_DIRECTORY_1, 1);

    if ($key !== null) {
        $key = $key[0];
        $results = array();
        $link = "https://www.patreon.com/api/oauth2/api/campaigns/" . PatreonVariables::CAMPAIGN_ID . "/pledges";

        while ($link !== null) {
            $cacheKey = array(
                $link,
                "patreon-1-links"
            );
            $reply = get_key_value_pair($cacheKey);

            if ($reply === null) {
                $timeout = 3;
                $limit = get_time_limit();

                if ($limit !== false && $limit > 0) {
                    set_time_limit($timeout + 2);
                }
                set_time_limit($timeout + 2);
                $reply = @json_decode(get_curl(
                    $link,
                    "GET",
                    array(
                        "Content-Type: application/json",
                        "Authorization: Bearer " . $key
                    ),
                    null,
                    $timeout
                ), false);

                if ($reply === false) {
                    set_key_value_pair($cacheKey, false, "15 seconds");
                    return array();
                } else {
                    set_key_value_pair($cacheKey, $reply, PatreonVariables::CACHE_TIME);
                }
            } else if ($reply === false) {
                return array();
            }

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
        set_key_value_pair($totalCacheKey, $results, PatreonVariables::CACHE_TIME);
        return $results;
    } else {
        return array();
    }
}

function get_patreon2_subscriptions(?array $ignoreTiers = null, ?array $targetTiers = null, ?bool $paid = true): array
{
    $totalCacheKey = array(
        $ignoreTiers,
        $targetTiers,
        $paid,
        "patreon-2-subscriptions"
    );
    $cache = get_key_value_pair($totalCacheKey);

    if (is_array($cache)) {
        return $cache;
    }
    $key = get_keys_from_file(PatreonVariables::CREDENTIALS_DIRECTORY_2, 1);

    if ($key !== null) {
        $key = $key[0];
        $results = array();
        $arguments = "currently_entitled_tiers,address";
        $arguments .= "&fields[member]=full_name,last_charge_date,next_charge_date,last_charge_status,lifetime_support_cents,currently_entitled_amount_cents,patron_status";
        $arguments .= "&fields[tier]=amount_cents,created_at,description,discord_role_ids,edited_at,patron_count,published,published_at,requires_shipping,title,url";
        $arguments .= "&fields[address]=addressee,city,line_1,line_2,phone_number,postal_code,state";
        $link = "https://www.patreon.com/api/oauth2/v2/campaigns/" . PatreonVariables::CAMPAIGN_ID . "/members?include="
            . str_replace("[", "%5B", str_replace("]", "%5D", $arguments));

        while ($link !== null) {
            $cacheKey = array(
                $link,
                "patreon-2-links"
            );
            $reply = get_key_value_pair($cacheKey);

            if ($reply === null) {
                $timeout = 3;
                $limit = get_time_limit();

                if ($limit !== false && $limit > 0) {
                    set_time_limit($timeout + 2);
                }
                $reply = @json_decode(get_curl(
                    $link,
                    "GET",
                    array(
                        "Content-Type: application/json",
                        "Authorization: Bearer " . $key
                    ),
                    null,
                    $timeout
                ), false);

                if ($reply === false) {
                    set_key_value_pair($cacheKey, false, "15 seconds");
                    return array();
                } else {
                    set_key_value_pair($cacheKey, $reply, PatreonVariables::CACHE_TIME);
                }
            } else if ($reply === false) {
                return array();
            }

            if (isset($reply->data)) {
                foreach ($reply->data as $patron) {
                    if (isset($patron->type)
                        && $patron->type == "member") {
                        if ($paid === null
                            || patreon_object_is_paid($patron) === $paid) {
                            if (patreon_object_has_tier($patron, $ignoreTiers, $targetTiers)) {
                                $results[] = $patron;
                            }
                        }
                    }
                }
            }
            $link = $reply->links->next ?? null;
        }
        set_key_value_pair($totalCacheKey, $results, PatreonVariables::CACHE_TIME);
        return $results;
    } else {
        return array();
    }
}

function patreon_object_has_tier(?object $object, ?array $ignoreTiers = null, ?array $targetTiers = null): bool
{
    if (isset($object->relationships->currently_entitled_tiers->data)) {
        $hasIgnoreTiers = !empty($ignoreTiers);
        $hasTargetTiers = !empty($targetTiers);

        foreach ($object->relationships->currently_entitled_tiers->data as $tier) {
            if ((!$hasIgnoreTiers || !in_array($tier->id, $ignoreTiers))
                && (!$hasTargetTiers || in_array($tier->id, $targetTiers))) {
                return true;
            }
        }
    }
    return false;
}

function patreon_object_is_paid(?object $object): bool
{
    return $object?->attributes?->patron_status == "active_patron";
}