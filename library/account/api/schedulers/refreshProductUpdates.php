<?php

if (function_exists("schedule_function_in_memory")) {

    function refresh_product_updates(): void
    {
        if (!has_session_account_id()) { // Staff team should avoid this delay
            private_file_get_contents(
                "http://" . Account::LOAD_BALANCER_IP . "/async/refreshProductUpdates/",
                true
            );
        }
    }

    schedule_function_in_memory(
        "refresh_product_updates",
        null,
        60,
        false
    );
}
