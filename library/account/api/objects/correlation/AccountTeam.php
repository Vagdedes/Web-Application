<?php

class AccountTeam
{

    private const
        PERMISSION_ADD_TEAM_MEMBERS = 1,
        PERMISSION_REMOVE_TEAM_MEMBERS = 2,
        PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS = 3,
        PERMISSION_CHANGE_TEAM_NAME = 4,
        PERMISSION_CHANGE_TEAM_DESCRIPTION = 5,
        PERMISSION_ADD_TEAM_MEMBER_PERMISSIONS = 6,
        PERMISSION_REMOVE_TEAM_MEMBER_PERMISSIONS = 7;

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
        if ($this->additionalID === null) {
            return new MethodReply(false, "Team additional ID not set.");
        }
        if ($account === null) {
            $account = $this->account;
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        $teams = get_sql_query(
            AccountVariables::TEAM_TABLE,
            null,
            array(
                array("additional_id", $this->additionalID),
                array("deletion_date", null)
            )
        );

        if (empty($teams)) {
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

    public function leaveTeam(Account $account): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    // Separator

    public function updateName(Account $account, string $name): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getPermission($this->account, self::PERMISSION_CHANGE_TEAM_NAME)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team name.");
        }
        if (sql_insert(
            AccountVariables::TEAM_NAME_CHANGES,
            array(
                "name" => $name,
                "creation_date" => get_current_date(),
                "created_by" => $this->account->getDetail("id"),
            ))) {
            if (set_sql_query(
                AccountVariables::TEAM_TABLE,
                array(
                    "name" => $name
                ),
                array(
                    array("id", $team)
                )
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to fully update team name.");
            }
        } else {
            return new MethodReply(false, "Failed to update team name.");
        }
    }

    public function updateDescription(Account $account, string $name): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getPermission($this->account, self::PERMISSION_CHANGE_TEAM_DESCRIPTION)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team description.");
        }
        if (sql_insert(
            AccountVariables::TEAM_NAME_CHANGES,
            array(
                "name" => $name,
                "creation_date" => get_current_date(),
                "created_by" => $this->account->getDetail("id"),
                "description" => true
            ))) {
            if (set_sql_query(
                AccountVariables::TEAM_TABLE,
                array(
                    "description" => $name
                ),
                array(
                    array("id", $team)
                )
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to fully update team description.");
            }
        } else {
            return new MethodReply(false, "Failed to update team description.");
        }
    }

    // Separator

    public function addMember(Account $account): MethodReply
    {
        /*if ($account->getDetail("id") == $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't add yourself to a team.");
        }*/
        $result = $this->getTeam($this->account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    public function removeMember(Account $account): MethodReply
    {
        /*if ($account->getDetail("id") == $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't remove yourself from a team.");
        }*/
        $result = $this->getTeam($this->account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        return new MethodReply(false);
    }

    public function getMembers(): array
    {
        $team = $this->getTeam($this->account)->getObject()?->id;

        if ($team === null) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            )
        );

        if (empty($query)) {
            return array();
        } else {
            $new = array();

            foreach ($query as $value) {
                if (!array_key_exists($value->account_id, $new)) {
                    $value->account = new Account($value->account_id);
                    $new[$value->account_id] = $value;
                }
            }
            foreach ($new as $key => $value) {
                if (!$value->account->exists()) {
                    unset($new[$key]);
                }
            }
            return $new;
        }
    }

    public function getMember(Account $account): ?object
    {
        $team = $this->getTeam($account)->getObject()?->id;

        if ($team === null) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("account_id", $account->getDetail("id")),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return null;
        } else {
            $query = $query[0];
            $query->account = new Account($query->account_id);

            if ($query->account->exists()) {
                return $query;
            } else {
                return null;
            }
        }
    }

    // Separator

    public function getPosition(Account $account): ?int
    {
        $team = $this->getTeam($account)->getObject()?->id;

        if ($team === null) {
            return null;
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_POSITIONS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        if (empty($query)) {
            return null;
        }
        return $query[0]->position;
    }

    public function adjustPosition(Account $account, int $position): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if ($account->getDetail("id") == $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't change your own team position.");
        }
        if (!$this->getPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change others positions.");
        }
        if ($this->getPosition($this->account) <= $this->getPosition($account)) {
            return new MethodReply(false, "Can't change position of someone with the same or higher position.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_POSITIONS_TABLE,
            array(
                "team_id" => $team,
                "member_id" => $memberID,
                "position" => $position,
                "creation_date" => get_current_date(),
                "created_by" => $this->account->getDetail("id")
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to change position.");
        }
    }

    public function adjustPositionByComparison(Account $account, Account|array $accountAgainst): MethodReply
    {
        if (is_array($accountAgainst)) {
            $max = 0;

            foreach ($accountAgainst as $loopAccount) {
                $position = $this->getPosition($loopAccount);

                if ($position === null) {
                    return new MethodReply(false, "Account position not found.");
                }
                if ($position > $max) {
                    $max = $position;
                }
            }
        } else {
            $max = $this->getPosition($accountAgainst);

            if ($max === null) {
                return new MethodReply(false, "Account position not found.");
            }
        }
        return $this->adjustPosition($account, $max + 1);
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

    public function addPermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        if (!$this->getPermission($this->account, self::PERMISSION_ADD_TEAM_MEMBER_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add others permissions.");
        }
        if ($this->getPosition($this->account) <= $this->getPosition($account)) {
            return new MethodReply(false, "Can't give permission to someone with the same or higher position.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if ($this->getPermission($account, $permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission already given.");
        }
        if (sql_insert(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "team_id" => $team->id,
                "member_id" => $memberID,
                "permission_id" => $permissionDef,
                "creation_date" => get_current_date(),
                "created_by" => $this->account->getDetail("id")
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to give permission.");
        }
    }

    public function removePermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        if ($this->getPosition($this->account) <= $this->getPosition($account)) {
            return new MethodReply(false, "Can't remove permission from someone with the same or higher position.");
        }
        if (!$this->getPermission($this->account, self::PERMISSION_REMOVE_TEAM_MEMBER_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove others permissions.");
        }
        if (!$this->getPermission($account, $permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $this->account->getDetail("id")
            ),
            array(
                array("team_id", $team),
                array("member_id", $memberID),
                array("permission_id", $permissionDef),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        )) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to remove permission.");
        }
    }

    public function getPermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID),
                array("permission_id", $permissionDef),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return new MethodReply(false, "Permission not given.");
        } else {
            $query = $query[0];
            return $query->deletion_date === null
                ? new MethodReply(true, $query)
                : new MethodReply(false, "Permission given and removed.");
        }
    }

    public function getPermissions(Account $account): array
    {
        $team = $this->getTeam($account)->getObject()?->id;

        if ($team === null) {
            return array();
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID),
            ),
            array(
                "DESC",
                "id"
            )
        );
        if (empty($query)) {
            return array();
        } else {
            $new = array();

            foreach ($query as $value) {
                if (!array_key_exists($value->permission_id, $new)) {
                    $new[$value->permission_id] = $value;
                }
            }
            foreach ($new as $key => $value) {
                if ($value->deletion_date !== null) {
                    unset($new[$key]);
                }
            }
            return $new;
        }
    }

}
