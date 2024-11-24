<?php

class StripeVariables
{
    public const
        CREDENTIALS_DIRECTORY = "stripe_credentials",
        SUCCESSFUL_TRANSACTIONS_TABLE = "stripe.successfulTransactions",
        FAILED_TRANSACTIONS_TABLE = "stripe.failedTransactions",
        TRANSACTION_SEARCH_KEYS = array("description"),
        ACCOUNT_AMOUNT = 2;
}
