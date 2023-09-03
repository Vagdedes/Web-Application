<?php

class AccountCorrelation
{
    private Account $account;
    //todo introduce constants that are null application-id

    public function __construct($account)
    {
        $this->account = $account;
    }
}