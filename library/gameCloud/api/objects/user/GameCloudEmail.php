<?php

class GameCloudEmail
{

    private GameCloudUser $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function send($case, $detailsArray = null, $type = "account", $unsubscribe = true): bool
    {
        $account = $this->user->getInformation()->getAccount();
        return $account->exists()
            && $account->getEmail()->isVerified()
            && $account->getEmail()->send($case, $detailsArray, $type, $unsubscribe);

    }
}