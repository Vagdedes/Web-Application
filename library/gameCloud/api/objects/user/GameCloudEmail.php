<?php

class GameCloudEmail
{

    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function send(int|string|float $case,
                         ?array           $detailsArray = null,
                         string           $type = "account",
                         bool             $unsubscribe = true): bool
    {
        $account = $this->user->getInformation()->getAccount();
        return $account->exists()
            && $account->getEmail()->isVerified()
            && $account->getEmail()->send($case, $detailsArray, $type, $unsubscribe);

    }
}