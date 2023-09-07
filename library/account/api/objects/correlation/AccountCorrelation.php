<?php

class AccountCorrelation
{
    private Account $account;

    //todo introduce constants that are null application-id

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function getType() {

    }

    // Separator

    public function createInstant() {

    }

    public function deleteInstant() {

    }

    public function getAllInstant() {

    }

    public function getSentInstant() {

    }

    public function getReceivedInstant() {

    }

    public function getInstant() {

    }

    // Separator

    public function createRequest() {

    }

    public function acceptRequest() {

    }

    public function denyRequest() {

    }

    public function deleteRequest() {

    }

    public function getAllRequests() {

    }

    public function getSentRequests() {

    }

    public function getReceivedRequests() {

    }

    public function getRequest() {

    }
}