<?php

class AccountReference
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

    public function getStorage()
    {

    }

    public function store()
    {

    }
}