<?php
$stripe_credentials_directory = "/var/www/.structure/private/stripe_credentials";

$stripe_transaction_search_keys = array("description");

$stripe_successful_transactions_table = "stripe.successfulTransactions";
$stripe_failed_transactions_table = "stripe.failedTransactions";
