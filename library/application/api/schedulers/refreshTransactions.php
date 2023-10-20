<?php
$refresh_transactions_run = true;
$refresh_transactions_function = "refresh_transactions";

function refresh_transactions(): void
{
    global $refresh_transactions_run;

    if ($refresh_transactions_run && !has_session_account_id()) { // Staff team should avoid this delay
        private_file_get_contents(
            "https://" . get_domain() . "/async/refreshTransactions/",
            true
        );
    }
}

schedule_function_in_memory(
    $refresh_transactions_function,
    null, 10,
    true,
    false
);
