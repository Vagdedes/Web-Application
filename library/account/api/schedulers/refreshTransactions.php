<?php

if (function_exists("schedule_function_in_memory")) {
    $refresh_transactions_function = "refresh_transactions";

    function refresh_transactions(): void
    {
        private_file_get_contents(
            "http://" . Account::LOAD_BALANCER_IP . "/async/refreshTransactions/",
            true
        );
    }

    schedule_function_in_memory(
        $refresh_transactions_function,
        null,
        2,
        false
    );
}
