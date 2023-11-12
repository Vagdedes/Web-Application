<?php

class AccountCooperation
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

    public function createVoting()
    {

    }

    public function deleteVoting()
    {

    }

    public function getVoting()
    {

    }

    public function getThread()
    {

    }

    // Separator

    public function makeChoice()
    {

    }

    public function deleteChoice()
    {

    }

    public function modifyChoice()
    {

    }

    // Separator

    public function getVotings()
    {

    }

    public function searchVotings()
    {

    }
}