<?php

class AccountTeam
{

    public const
        NAME_TITLE = 1,
        NAME_DESCRIPTION = 2;

    public const
        PERMISSION_ADD_TEAM_MEMBERS = 1,
        PERMISSION_REMOVE_TEAM_MEMBERS = 2,
        ADJUST_TEAM_MEMBER_POSITIONS = 3,
        CHANGE_TEAM_NAME = 4,
        CHANGE_TEAM_DESCRIPTION = 5;

    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    // Separator

    public function create()
    {

    }

    public function delete()
    {

    }

    // Separator

    public function updateTitle()
    {

    }

    public function updateDescription()
    {

    }

    // Separator

    public function addMember()
    {

    }

    public function removeMember()
    {

    }

    // Separator

    private function getPermissionDefinition()
    {

    }

    public function givePermission()
    {

    }

    public function removePermission()
    {

    }

    public function hasPermission()
    {

    }

}
