<?php

class AccountStatistics
{
    private Account $account;

    public const
        INTEGER = 0,
        LONG = 1,
        DOUBLE = 2,
        STRING = 3,
        BOOLEAN = 4;

    //todo introduce constants that are null application-id

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function getType()
    {

    }

    // Separator

    public function get() {

    }

    public function set() {

    }

    public function add() {

    }

    public function delete() {

    }

    public function exists() {

    }

    public function list() {

    }
}