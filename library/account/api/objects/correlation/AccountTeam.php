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

    // Separator

    public function createTeam(string $name, string $description): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        $result = $this->getTeam($this->account);

        if ($result->isPositiveOutcome()) {
            return new MethodReply(false, "User is already in a team.");
        }
        // todo
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
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            null,
            array(
                array("account_id", $account->getDetail("id"))
            ),
            array(
                "DESC",
                "id"
            )
        );

        if (!empty($query)) {
            foreach ($query as $value) {
                $subQuery = get_sql_query(
                    AccountVariables::TEAM_TABLE,
                    null,
                    array(
                        array("id", $value->team_id),
                        array("additional_id", $this->additionalID),
                        array("deletion_date", null)
                    ),
                    null,
                    1
                );

                if (!empty($subQuery)) {
                    return new MethodReply(true, null, $subQuery[0]);
                }
            }
        }
        return new MethodReply(false, "Team not found.");
    }

    public function deleteTeam(): MethodReply
    {
        $result = $this->getTeam($this->account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found.");
        }
        if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
            return new MethodReply(false, "Must be the owner to delete this team.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $this->account->getDetail("id")
            ),
            array(
                array("id", $team->id)
            ),
            null,
            1
        )) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to delete team.");
        }
    }

    // Separator

    public function updateName(string $name): MethodReply
    {
        $result = $this->getTeam($this->account);
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
                ),
                null,
                1
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to fully update team name.");
            }
        } else {
            return new MethodReply(false, "Failed to update team name.");
        }
    }

    public function updateDescription(string $name): MethodReply
    {
        $result = $this->getTeam($this->account);
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
                ),
                null,
                1
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

    public function leaveTeam(): MethodReply
    {
        $result = $this->getTeam($this->account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $memberCount = sizeof($this->getMembers());

        if ($memberCount === 1) {
            return $this->deleteTeam();
        } else {
            $memberID = $this->getMember($this->account)?->id;

            if ($memberID === null) {
                return new MethodReply(false, "Member not found.");
            }
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "Owner not found.");
            }
            if ($owner->account->getDetail("id") === $this->account->getDetail("id")) {
                $newOwner = $this->getOwner($this->account);

                if ($newOwner === null) {
                    return new MethodReply(false, "New owner not found.");
                }
                $adjust = $this->adjustPositionByComparison(
                    $newOwner->account,
                    $owner->account
                );

                if (!$adjust->isPositiveOutcome()) {
                    return $adjust;
                }
            }
            if (set_sql_query(
                AccountVariables::TEAM_MEMBERS_TABLE,
                array(
                    "deletion_date" => get_current_date(),
                ),
                array(
                    array("id", $memberID)
                ),
                null,
                1
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to leave team.");
            }
        }
    }

    public function addMember(Account $account): MethodReply
    {
        $result = $this->getTeam($this->account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        if (!$this->getPermission($this->account, self::PERMISSION_ADD_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add members to the team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't add yourself to a team.");
        }
        $otherResult = $this->getTeam($account);

        if ($otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "User is already in a team.");
        }
        if (sql_insert(
            AccountVariables::TEAM_MEMBERS_TABLE,
            array(
                "team_id" => $team->id,
                "account_id" => $account->getDetail("id"),
                "creation_date" => get_current_date(),
                "created_by" => $this->account->getDetail("id")
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to add member to team.");
        }
    }

    public function removeMember(Account $account): MethodReply
    {
        $result = $this->getTeam($account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        $otherResult = $this->getTeam($this->account);
        $otherTeam = $otherResult->getObject();

        if ($otherTeam === null) {
            return $otherResult;
        }
        if (!$this->getPermission($this->account, self::PERMISSION_REMOVE_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove members from the team.");
        }
        if ($team->id !== $otherTeam->id) {
            return new MethodReply(false, "Can't remove someone in another team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't remove yourself from a team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $this->account->getDetail("id")
            ),
            array(
                array("id", $memberID)
            ),
            null,
            1
        )) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to remove member from team.");
        }
    }

    public function getOwner(?Account $exclude = null): ?object
    {
        if ($exclude !== null && !$exclude->exists()) {
            return null;
        }
        $members = $this->getMembers();

        if (empty($members)) {
            return null;
        } else {
            $max = null;
            $owner = null;

            foreach ($members as $member) {
                if ($exclude === null || $member->account->getDetail("id") !== $exclude->getDetail("id")) {
                    $position = $this->getPosition($member->account);

                    if ($max === null || $position > $max) {
                        $max = $position;
                        $owner = $member;
                    }
                }
            }
            return $owner;
        }
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
            null,
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
        $memberID = $this->getMember($account);

        if ($memberID === null) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_POSITIONS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID->id),
                array("creation_date", ">=", $memberID->creation_date)
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
        $otherResult = $this->getTeam($this->account);
        $otherTeam = $otherResult->getObject();

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team->id !== $otherTeam->id) {
            return new MethodReply(false, "Can't adjust position of someone in another team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't change your own position in team.");
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
            $max = null;

            foreach ($accountAgainst as $loopAccount) {
                $position = $this->getPosition($loopAccount);

                if ($position === null) {
                    return new MethodReply(false, "Account position not found.");
                }
                if ($max === null || $position > $max) {
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
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't add permissions to yourself.");
        }
        $otherResult = $this->getTeam($this->account);
        $otherTeam = $otherResult->getObject();

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team->id !== $otherTeam->id) {
            return new MethodReply(false, "Can't add permission to someone in another team.");
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
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't remove your own permissions.");
        }
        $otherResult = $this->getTeam($this->account);
        $otherTeam = $otherResult->getObject();

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team->id !== $otherTeam->id) {
            return new MethodReply(false, "Can't remove permission from someone in another team.");
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
        $permissionResult = $this->getPermission($account, $permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $object = $permissionResult->getObject();

        if ($object === null) {
            return new MethodReply(false, "Permission object not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $this->account->getDetail("id")
            ),
            array(
                array("id", $object->id),
            ),
            null,
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
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found.");
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            return new MethodReply(true);
        }
        $memberID = $this->getMember($account);

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
                array("member_id", $memberID->id),
                array("permission_id", $permissionDef),
                array("creation_date", ">=", $memberID->creation_date)
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
                ? new MethodReply(true, null, $query)
                : new MethodReply(false, "Permission given and removed.");
        }
    }

    public function getPermissions(Account $account): array
    {
        $team = $this->getTeam($account)->getObject()?->id;

        if ($team === null) {
            return array();
        }
        $memberID = $this->getMember($account);

        if ($memberID === null) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID->id),
                array("creation_date", ">=", $memberID->creation_date)
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
