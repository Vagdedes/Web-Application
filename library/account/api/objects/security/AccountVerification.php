<?php

class AccountVerification
{
    private Account $account;

    //todo introduce constants that are null application-id

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getType()
    {

    }

    // Separator

    public function createInstantVerification()
    {

    }

    public function deleteInstantVerification()
    {

    }

    public function getAllInstantVerifications()
    {

    }

    public function getSentInstantVerifications()
    {

    }

    public function getReceivedInstantVerifications()
    {

    }

    // Separator

    public function createPasswordVerification()
    {

    }

    public function deletePasswordVerification()
    {

    }

    public function completePasswordVerification()
    {

    }

    public function getAllPasswordVerifications()
    {

    }

    public function getSentPasswordVerifications()
    {

    }

    public function getReceivedPasswordVerifications()
    {

    }
}