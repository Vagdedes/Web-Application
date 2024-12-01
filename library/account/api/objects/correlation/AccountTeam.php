<?php

class AccountTeam
{

    private const
        NAME_TITLE = 1,
        NAME_DESCRIPTION = 2;

    public const
        PERMISSION_ADD_TEAM_MEMBERS = 1,
        PERMISSION_REMOVE_TEAM_MEMBERS = 2,
        ADJUST_TEAM_MEMBER_POSITIONS = 3,
        CHANGE_TEAM_NAME = 4,
        CHANGE_TEAM_DESCRIPTION = 5;

    private Account $account;
    private ?int $additionalID;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->additionalID = null;
    }

    // Separator

    public function setTeamAdditionalID(int $additionalID): void
    {
        $this->additionalID = $additionalID;
    }

    public function createTeam(): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        return new MethodReply(false);
    }

    public function getTeam(?Account $account = null): MethodReply
    {
        if ($account === null) {
            $account = $this->account;
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        $team = get_sql_query(
            AccountVariables::TEAM_TABLE,
            null,
            array(
                array("account_id", $account->id),
                array("additional_id", $this->additionalID),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($team)) {
            return new MethodReply(false, "Team not found.");
        }
        $team = $team[0];

        if ($team->deletion_date !== null) {
            return new MethodReply(false, "Team has been deleted.");
        }
        return new MethodReply(false);
    }

    public function deleteTeam(Account $account): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    // Separator

    public function updateTitle(Account $account, string $name): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    public function updateDescription(Account $account, string $name): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    // Separator

    public function addMember(Account $account): MethodReply
    {
        if ($account->getDetail("id") == $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't add yourself to a team.");
        }
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    public function removeMember(Account $account): MethodReply
    {
        if ($account->getDetail("id") == $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't remove yourself from a team.");
        }
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    public function getMembers(): array
    {
        $team = $this->getTeam($this->account)->getObject();

        if ($team === null) {
            return array();
        }
        return array();
    }

    public function getMember(Account $account): ?object
    {
        $team = $this->getTeam($account)->getObject();

        if ($team === null) {
            return null;
        }
        return null;
    }

    // Separator

    private function getPermissionDefinition(int $id): ?object
    {
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSION_DEFINITIONS_TABLE,
            null,
            array(
                array("id", $id),
                array("application_id", $this->account->getDetail("application_id")),
                array("deletion_date", null)
            ),
            null,
            1
        );
        return empty($query) ? null : $query[0];
    }

    public function givePermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID);

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        return new MethodReply(false);
    }

    public function removePermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID);

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        return new MethodReply(false);
    }

    public function getPermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID);

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $team->id),
                array("member_id", $memberID),
                array("permission_id", $permissionDef->id),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($query)) {
            return new MethodReply(false, "Permission not given.");
        } else {
            return new MethodReply(true, $query[0]);
        }
    }

    public function getPermissions(Account $account): array
    {
        $team = $this->getTeam($account)->getObject();

        if ($team === null) {
            return array();
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return array();
        }
        return get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $team->id),
                array("member_id", $memberID),
                array("deletion_date", null)
            )
        );
    }

}
