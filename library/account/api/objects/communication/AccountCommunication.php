<?php

class AccountCommunication
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

    public function createThread() {
    }

    public function deleteThread() {

    }

    public function inviteToThread() {

    }

    public function removeFromThread() {

    }

    public function getThread() {

    }

    // Separator

    public function sendMessage() {

    }

    public function modifyMessage() {

    }

    public function deleteMessage() {

    }

    // Separator

    public function getThreads() {

    }

    public function searchThreads() {

    }
}