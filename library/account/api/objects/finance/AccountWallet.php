<?php

class AccountWallet
{
    private Account $account;

    // todo introduce constants that are null application-id

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getTransactionType()
    {

    }

    public function getSupportedCurrencies()
    {

    }

    // Separator

    public function openWallet()
    {

    }

    public function closeWallet()
    {

    }

    public function getWallets()
    {

    }

    public function getWallet()
    {

    }

    // Separator

    public function addAcceptedCurrency()
    {

    }

    public function removeAcceptedCurrency()
    {

    }

    public function addDeniedCurrency()
    {

    }

    public function removeDeniedCurrency()
    {

    }

    public function getAcceptedCurrencies()
    {

    }

    public function getDeniedCurrencies()
    {

    }

    // Separator

    public function createInstantTransfer()
    {

    }

    public function createTransfer()
    {

    }

    public function acceptTransfer()
    {

    }

    public function denyTransfer()
    {

    }

    public function deleteTransfer()
    {

    }

    public function getInstantTransfers()
    {

    }

    public function getTransfers()
    {

    }

    public function getInstantTransfer()
    {

    }

    public function getTransfer()
    {

    }

    // Separator

    public function createRequest()
    {

    }

    public function acceptRequest()
    {

    }

    public function denyRequest()
    {

    }

    public function deleteRequest()
    {

    }

    public function getRequest()
    {

    }

    // Separator

    public function createTransaction()
    {

    }

    public function getTransactions()
    {

    }

    public function getTransaction()
    {

    }

    public function searchTransaction()
    {

    }
}