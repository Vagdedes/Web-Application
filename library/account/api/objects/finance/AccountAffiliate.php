<?php

class AccountAffiliate
{
    private Account $account;

    //todo introduce constants that are null application-id

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getCampaign()
    {

    }

    public function createCampaign()
    {

    }

    public function deleteCampaign()
    {

    }

    public function completeCampaign()
    {

    }

    public function failCampaign()
    {

    }
}