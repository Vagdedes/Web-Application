<?php

class PayPalVariables
{
    public const
        CREDENTIALS_DIRECTORY = "paypal_credentials",

        SUCCESSFUL_TRANSACTIONS_TABLE = "paypal.successfulTransactions",
        FAILED_TRANSACTIONS_TABLE = "paypal.failedTransactions",
        TRANSACTIONS_QUEUE_TABLE = "paypal.queue",
        SUSPENDED_TRANSACTIONS_TABLE = "paypal.suspendedTransactions",

        ME_URL = "https://paypal.me/",
        ME_NAME_PERSONAL = "EvangelosBilling",
        ME_NAME_BUSINESS = "IdealisticAI";
}
