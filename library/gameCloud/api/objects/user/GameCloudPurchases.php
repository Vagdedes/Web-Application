<?php

class GameCloudPurchases
{
    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function ownsManually(
        string $email,
        string $dataDirectory
    ): bool
    {
        return !empty(get_sql_query(
            GameCloudVariables::PURCHASES_TABLE,
            array(
                "email_address"
            ),
            array(
                array("email_address", $email),
                array("data_directory", $dataDirectory),
                array("deletion_date", null)
            ),
            null,
            1
        ));
    }

}
