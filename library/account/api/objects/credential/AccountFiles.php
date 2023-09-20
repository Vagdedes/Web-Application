<?php

class AccountFiles
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

    public function upload() {

    }

    public function delete() {

    }

    public function getAll() {

    }

    public function getOwned() {

    }

    public function getShared() {

    }

    // Separator

    public function share() {

    }

    public function unshare() {

    }

    // Separator

    public function download() {

    }
}