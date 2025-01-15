<?php

class AccountTeam
{

    public const
        DEFAULT_ADDITIONAL_ID = null,
        BIGMANAGE_ADDITIONAL_ID = 0,

        // Members
        PERMISSION_ADD_TEAM_MEMBERS = 1,
        PERMISSION_REMOVE_TEAM_MEMBERS = 2,

        // Roles
        PERMISSION_CREATE_TEAM_ROLES = 8,
        PERMISSION_DELETE_TEAM_ROLES = 9,

        // Positions
        PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS = 3,
        PERMISSION_ADJUST_TEAM_ROLE_POSITIONS = 10,

        // Names
        PERMISSION_CHANGE_TEAM_NAME = 4,
        PERMISSION_CHANGE_TEAM_ROLE_NAMES = 16,

        // Descriptions
        PERMISSION_CHANGE_TEAM_DESCRIPTION = 5,
        PERMISSION_CHANGE_TEAM_ROLE_DESCRIPTIONS = 17,

        // Permissions
        PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS = 14,
        PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS = 15,

        // Grouping
        PERMISSION_ADJUST_TEAM_MEMBER_ROLES = 13,

        PERMISSION_ALL = array(
        self::PERMISSION_ADD_TEAM_MEMBERS,
        self::PERMISSION_REMOVE_TEAM_MEMBERS,
        self::PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS,
        self::PERMISSION_CHANGE_TEAM_NAME,
        self::PERMISSION_CHANGE_TEAM_DESCRIPTION,
        self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS,
        self::PERMISSION_CREATE_TEAM_ROLES,
        self::PERMISSION_DELETE_TEAM_ROLES,
        self::PERMISSION_ADJUST_TEAM_ROLE_POSITIONS,
        self::PERMISSION_ADJUST_TEAM_MEMBER_ROLES,
        self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS,
        self::PERMISSION_CHANGE_TEAM_ROLE_NAMES,
        self::PERMISSION_CHANGE_TEAM_ROLE_DESCRIPTIONS
    );

    private Account $account;
    private ?int $additionalID;
    private ?object $forcedTeam;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->additionalID = self::DEFAULT_ADDITIONAL_ID;
        $this->forcedTeam = null;
    }

    // Separator

    public function setTeamAdditionalID(int $additionalID): void
    {
        $this->additionalID = $additionalID;
    }

    public function setForcedTeam(object $team): void
    {
        $this->forcedTeam = $team;
    }

    public function removeForcedTeam(): void
    {
        $this->forcedTeam = null;
    }

    // Separator

    public function createTeam(string $title, string $description, ?string $reason = null): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        $result = $this->findTeam($this->account);

        if ($result->isPositiveOutcome()) {
            return new MethodReply(false, "Account is already in a team.");
        }
        $date = get_current_date();

        if (sql_insert(
            AccountVariables::TEAM_TABLE,
            array(
                "additional_id" => $this->additionalID,
                "title" => $title,
                "description" => $description,
                "creation_date" => $date,
                "created_by_account" => $this->account->getDetail("id"),
                "creation_reason" => $reason
            ))) {
            $query = get_sql_query(
                AccountVariables::TEAM_TABLE,
                null,
                array(
                    array("additional_id", $this->additionalID),
                    array("deletion_date", null),
                    array("title", $title),
                    array("description", $description),
                    array("creation_date", $date),
                    array("created_by_account", $this->account->getDetail("id")),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (empty($query)) {
                return new MethodReply(false, "Failed to find created team.");
            } else {
                $query = $query[0]->id;

                if (sql_insert(
                    AccountVariables::TEAM_MEMBERS_TABLE,
                    array(
                        "team_id" => $query,
                        "account_id" => $this->account->getDetail("id"),
                        "creation_date" => $date,
                    ))) {
                    $selfMemberID = $this->getMember($this->account)?->id;

                    if ($selfMemberID === null) {
                        return new MethodReply(false, "Executor member not found.");
                    }
                    if (sql_insert(
                        AccountVariables::TEAM_POSITIONS_TABLE,
                        array(
                            "team_id" => $query,
                            "member_id" => $selfMemberID,
                            "position" => 0,
                            "creation_date" => $date,
                        ))) {
                        return new MethodReply(true);
                    } else {
                        return new MethodReply(false, "Failed to set member position in team.");
                    }
                } else {
                    return new MethodReply(false, "Failed to add member to team.");
                }
            }
        } else {
            return new MethodReply(false, "Failed to create team.");
        }
    }

    public function findTeams(Account $account = null): array
    {
        if ($account === null) {
            $account = $this->account;
        }
        if ($account->exists()) {
            $query = get_sql_query(
                AccountVariables::TEAM_MEMBERS_TABLE,
                null,
                array(
                    array("account_id", $account->getDetail("id")),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                )
            );

            if (!empty($query)) {
                $new = array();

                foreach ($query as $value) {
                    if (!in_array($value->team_id, $new)) {
                        $new[] = $value->team_id;
                    }
                }
                $result = array();

                foreach ($new as $value) {
                    $subQuery = get_sql_query(
                        AccountVariables::TEAM_TABLE,
                        null,
                        array(
                            array("id", $value),
                            array("additional_id", $this->additionalID),
                            array("deletion_date", null)
                        ),
                        null,
                        1
                    );

                    if (!empty($subQuery)) {
                        $result[] = $subQuery[0];
                    }
                }
                return $result;
            }
        }
        return array();
    }

    public function findTeam(Account|string|int|null $reference = null): MethodReply
    {
        if ($this->forcedTeam !== null) {
            if (isset($this->forcedTeam->id)) {
                $query = get_sql_query(
                    AccountVariables::TEAM_TABLE,
                    null,
                    array(
                        array("id", $this->forcedTeam->id),
                        array("additional_id", $this->additionalID),
                        array("deletion_date", null)
                    ),
                    null,
                    1
                );

                if (empty($query)) {
                    return new MethodReply(false, "Forced team not found.");
                } else {
                    return new MethodReply(true, null, $query[0]);
                }
            } else {
                return new MethodReply(false, "Forced team ID not set.");
            }
        }
        if ($reference === null) {
            $reference = $this->account;
        }
        if ($reference instanceof Account) {
            if (!$reference->exists()) {
                return new MethodReply(false, "Account not found.");
            }
            $query = get_sql_query(
                AccountVariables::TEAM_MEMBERS_TABLE,
                null,
                array(
                    array("account_id", $reference->getDetail("id")),
                    array("deletion_date", null)
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
        } else if (is_string($reference)) {
            $query = get_sql_query(
                AccountVariables::TEAM_TABLE,
                null,
                array(
                    array("title", $reference),
                    array("additional_id", $this->additionalID),
                    array("deletion_date", null)
                ),
                null,
                1
            );

            if (empty($query)) {
                return new MethodReply(false, "Team not found.");
            } else {
                return new MethodReply(true, null, $query[0]);
            }
        } else {
            $query = get_sql_query(
                AccountVariables::TEAM_TABLE,
                null,
                array(
                    array("id", $reference),
                    array("additional_id", $this->additionalID),
                    array("deletion_date", null)
                ),
                null,
                1
            );

            if (empty($query)) {
                return new MethodReply(false, "Team not found.");
            } else {
                return new MethodReply(true, null, $query[0]);
            }
        }
    }

    public function deleteTeam(?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

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
                "deleted_by" => $owner->id,
                "deletion_reason" => $reason
            ),
            array(
                array("id", $team)
            ),
            null,
            1
        )) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to delete team.");
        }
    }

    public function transferTeam(Account $account, ?string $reason = null): MethodReply
    {
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found or user not in a team.");
        }
        if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
            return new MethodReply(false, "Must be the owner of this team to transfer it.");
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            return new MethodReply(false, "You already own this team.");
        }
        $position = $this->getPosition($this->account, false);

        if ($position === null) {
            return new MethodReply(false, "Position not found.");
        }
        return $this->adjustMemberPosition(
            $account,
            $position + 1,
            $reason,
        );
    }

    // Separator

    public function updateTeamTitle(string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_NAME)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team title.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_NAME_CHANGES,
            array(
                "name" => $name,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            if (set_sql_query(
                AccountVariables::TEAM_TABLE,
                array(
                    "title" => $name
                ),
                array(
                    array("id", $team)
                ),
                null,
                1
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to fully update team title.");
            }
        } else {
            return new MethodReply(false, "Failed to update team title.");
        }
    }

    public function updateTeamDescription(string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_DESCRIPTION)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team description.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_NAME_CHANGES,
            array(
                "name" => $name,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason,
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

    public function leaveTeam(?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
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
                    "deletion_reason" => $reason
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

    public function addMember(Account $account, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject();

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADD_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add members to the team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot add yourself to a team.");
        }
        $otherResult = $this->findTeam($account);

        if ($otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "Account is already in a team.");
        }
        $rookie = $this->getRookie();

        if ($rookie === null) {
            return new MethodReply(false, "Rookie not found.");
        }
        $rookiePosition = $this->getPosition($rookie->account);

        if ($rookiePosition === null) {
            return new MethodReply(false, "Rookie position not found.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $date = get_current_date();

        if (sql_insert(
            AccountVariables::TEAM_MEMBERS_TABLE,
            array(
                "team_id" => $team->id,
                "account_id" => $account->getDetail("id"),
                "creation_date" => $date,
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            $memberID = $this->getMember($account)?->id;

            if ($memberID === null) {
                return new MethodReply(false, "Member not found.");
            }
            if (sql_insert(
                AccountVariables::TEAM_POSITIONS_TABLE,
                array(
                    "team_id" => $team->id,
                    "account_id" => $memberID,
                    "position" => $rookiePosition,
                    "creation_date" => $date,
                    "created_by" => $selfMemberID
                ))) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to set member position in team.");
            }
        } else {
            return new MethodReply(false, "Failed to add member to team.");
        }
    }

    public function removeMember(Account $account, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        $otherResult = $this->findTeam($this->account);
        $otherTeam = $otherResult->getObject()?->id;

        if ($otherTeam === null) {
            return $otherResult;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_REMOVE_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove members from the team.");
        }
        if ($team !== $otherTeam) {
            return new MethodReply(false, "Cannot remove someone in another team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot remove yourself from a team.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $selfMemberID,
                "deletion_reason" => $reason
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

    public function getMembers(): array
    {
        $team = $this->findTeam($this->account)->getObject()?->id;

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

    public function getMember(?Account $account = null): ?object
    {
        if ($account === null) {
            $account = $this->account;
        }
        $team = $this->findTeam($account)->getObject()?->id;

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

    public function getRookie(): ?object
    {
        $members = $this->getMembers();

        if (empty($members)) {
            return null;
        } else {
            $min = null;
            $rookie = null;

            foreach ($members as $member) {
                $position = $this->getPosition($member->account);

                if ($min === null || $position < $min) {
                    $min = $position;
                    $rookie = $member;
                }
            }
            return $rookie;
        }
    }

    // Separator

    public function getPosition(?Account $account = null, bool $setOwnerToMax = true): ?int
    {
        if ($account === null) {
            $account = $this->account;
        }
        $team = $this->findTeam($account)->getObject()?->id;

        if ($team === null) {
            return null;
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return null;
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return null;
        }
        if ($setOwnerToMax
            && $owner->account->getDetail("id") === $account->getDetail("id")) {
            global $max_32bit_Integer;
            return $max_32bit_Integer;
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
            $roles = $this->getMemberRoles($account);

            if (empty($roles)) {
                return null;
            } else {
                $max = null;

                foreach ($roles as $role) {
                    $position = $this->getRolePosition($role);

                    if ($max === null || $position > $max) {
                        $max = $position;
                    }
                }
                return $max;
            }
        } else {
            $roles = $this->getMemberRoles($account);

            if (empty($roles)) {
                return $query[0]->position;
            } else {
                $max = null;

                foreach ($roles as $role) {
                    $position = $this->getRolePosition($role);

                    if ($max === null || $position > $max) {
                        $max = $position;
                    }
                }
                return max($query[0]->position, $max);
            }
        }
    }

    public function adjustMemberPosition(Account $account, int $position, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        $otherResult = $this->findTeam($this->account);
        $otherTeam = $otherResult->getObject()?->id;

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team !== $otherTeam) {
            return new MethodReply(false, "Cannot adjust position of someone in no or another team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "Owner not found.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "You cannot change your own position in team.");
            }
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change others positions.");
        }
        $userPosition = $this->getPosition($this->account);
        $message = "Cannot change the position of a member with the same or higher position.";

        if ($userPosition === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getPosition($account);

        if ($otherPosition === null
            || $userPosition <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        if ($userPosition <= $position) {
            return new MethodReply(false, "Cannot change the position to the your or a higher position level.");
        }
        global $max_32bit_Integer;

        if ($position === $max_32bit_Integer) {
            return new MethodReply(false, "Cannot change the position to the highest possible level.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
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
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to change position.");
        }
    }

    public function adjustPositionByComparison(
        object       $reference,
        object|array $against,
        bool         $above = true,
        ?string      $reason = null
    ): MethodReply
    {
        if (is_array($against)) {
            $max = null;

            foreach ($against as $loopObject) {
                if ($against instanceof Account) {
                    $position = $this->getPosition($loopObject);

                    if ($position === null) {
                        return new MethodReply(false, "Account position not found.");
                    }
                } else {
                    $position = $this->getRolePosition($loopObject);

                    if ($position === null) {
                        return new MethodReply(false, "Role position not found.");
                    }
                }
                if ($max === null || $position > $max) {
                    $max = $position;
                }
            }
        } else if ($against instanceof Account) {
            $max = $this->getPosition($against);

            if ($max === null) {
                return new MethodReply(false, "Account position not found.");
            }
        } else {
            $max = $this->getRolePosition($against);

            if ($max === null) {
                return new MethodReply(false, "Role position not found.");
            }
        }
        return $reference instanceof Account
            ? $this->adjustMemberPosition($reference, $max + ($above ? 1 : -1), $reason)
            : $this->adjustRolePosition($reference, $max + ($above ? 1 : -1), $reason);
    }

    // Separator

    public function updateRoleTitle(string|object $role, string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_ROLE_NAMES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team names.");
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot change a role's name with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getRolePosition($role);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_ROLE_NAME_CHANGES,
            array(
                "name" => $name,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            if (set_sql_query(
                AccountVariables::TEAM_ROLES_TABLE,
                array(
                    "title" => $name
                ),
                array(
                    array("id", $role->id)
                ),
                null,
                1
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to fully update the team role's title.");
            }
        } else {
            return new MethodReply(false, "Failed to update the team role's title.");
        }
    }

    public function updateRoleDescription(string|object $role, string $description, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_ROLE_DESCRIPTIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team names.");
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot change a role's name with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getRolePosition($role);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_ROLE_NAME_CHANGES,
            array(
                "name" => $description,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason,
                "description" => true
            ))) {
            if (set_sql_query(
                AccountVariables::TEAM_ROLES_TABLE,
                array(
                    "description" => $description
                ),
                array(
                    array("id", $role->id)
                ),
                null,
                1
            )) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to fully update the team role's description.");
            }
        } else {
            return new MethodReply(false, "Failed to update the team role's description.");
        }
    }

    public function createRole(string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CREATE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to create team roles.");
        }
        $role = $this->getRole($name);

        if ($role !== null) {
            return new MethodReply(false, "Role with this already exists.");
        }
        $rookie = $this->getRookieRole();

        if ($rookie === null) {
            $rookie = 0;
        } else {
            $rookie = $this->getRolePosition($rookie);

            if ($rookie === null) {
                return new MethodReply(false, "Rookie role position not found.");
            }
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $date = get_current_date();

        if (sql_insert(
            AccountVariables::TEAM_ROLES_TABLE,
            array(
                "team_id" => $team,
                "title" => $name,
                "creation_date" => $date,
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            $role = $this->getRole($name);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
            if (sql_insert(
                AccountVariables::TEAM_ROLE_POSITIONS_TABLE,
                array(
                    "team_id" => $team,
                    "role_id" => $role->id,
                    "position" => $rookie,
                    "creation_date" => $date,
                    "created_by" => $selfMemberID
                ))) {
                return new MethodReply(true);
            } else {
                return new MethodReply(false, "Failed to set role position in team.");
            }
        } else {
            return new MethodReply(false, "Failed to create role.");
        }
    }

    public function deleteRole(string|object $role, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_DELETE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to delete team roles.");
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot delete a role with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getRolePosition($role);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $selfMemberID,
                "deletion_reason" => $reason
            ),
            array(
                array("id", $role->id)
            ),
            null,
            1
        )) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to delete role.");
        }
    }

    public function getMemberRoles(?Account $account = null): array
    {
        if ($account === null) {
            $account = $this->account;
        }
        $team = $this->findTeam($account)->getObject()?->id;

        if ($team === null) {
            return array();
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_MEMBERS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID),
                array("deletion_date", null)
            )
        );

        if (empty($query)) {
            return array();
        } else {
            $new = array();

            foreach ($query as $value) {
                $childQuery = get_sql_query(
                    AccountVariables::TEAM_ROLES_TABLE,
                    null,
                    array(
                        array("team_id", $team),
                        array("id", $value->role_id),
                        array("deletion_date", null)
                    ),
                    null,
                    1
                );

                if (!empty($childQuery)) {
                    $childQuery = $childQuery[0];
                    $childQuery->query = $value;
                    $new[] = $childQuery;
                }
            }
            return $new;
        }
    }

    public function getRole(string|int $reference): ?object
    {
        $team = $this->findTeam($this->account)->getObject()?->id;

        if ($team === null) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            null,
            array(
                array("team_id", $team),
                is_numeric($reference) ? array("id", $reference) : array("title", $reference),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        }
        return $query[0];
    }

    public function getTeamRoles(): array
    {
        $team = $this->findTeam($this->account)->getObject()?->id;

        if ($team === null) {
            return array();
        }
        return get_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            null,
            array(
                array("team_id", $team),
                array("deletion_date", null)
            )
        );
    }

    public function setRole(Account $account, string|object $role, bool $trueFalse, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change roles of members.");
        }
        $userPosition = $this->getPosition($this->account);
        $message = "Cannot change the role of a member with the same or higher position.";

        if ($userPosition === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getPosition($account);

        if ($otherPosition === null
            || $userPosition <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        $otherResult = $this->findTeam($account);
        $otherTeam = $otherResult->getObject()?->id;

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team !== $otherTeam) {
            return new MethodReply(false, "Cannot manage the role of someone in no or another team.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $roles = $this->getMemberRoles($account);

        if ($trueFalse) {
            foreach ($roles as $value) {
                if ($value->id === $role->id) {
                    return new MethodReply(false, "Role already given to member.");
                }
            }
            if (sql_insert(
                AccountVariables::TEAM_ROLE_MEMBERS_TABLE,
                array(
                    "team_id" => $team,
                    "role_id" => $role->id,
                    "member_id" => $memberID,
                    "creation_date" => get_current_date(),
                    "created_by" => $selfMemberID,
                    "creation_reason" => $reason
                ))) {
                return new MethodReply(true, "Role given to member.");
            } else {
                return new MethodReply(false, "Failed to give role to member.");
            }
        } else {
            if (!empty($roles)) {
                foreach ($roles as $value) {
                    if ($value->id === $role->id) {
                        if (set_sql_query(
                            AccountVariables::TEAM_ROLE_MEMBERS_TABLE,
                            array(
                                "deletion_date" => get_current_date(),
                                "deleted_by" => $selfMemberID
                            ),
                            array(
                                array("id", $value->query->id),
                            ),
                            null,
                            1
                        )) {
                            return new MethodReply(true, "Role removed from member.");
                        } else {
                            return new MethodReply(false, "Failed to remove role from member.");
                        }
                    }
                }
            }
            return new MethodReply(false, "Role not given to member.");
        }
    }

    private function getRookieRole(): ?object
    {
        $roles = $this->getTeamRoles();

        if (empty($roles)) {
            return null;
        } else {
            $min = null;
            $rookie = null;

            foreach ($roles as $role) {
                $position = $this->getRolePosition($role);

                if ($min === null || $position < $min) {
                    $min = $position;
                    $rookie = $role;
                }
            }
            return $rookie;
        }
    }

    public function getRolePermission(string|object $role, int $permissionID): MethodReply
    {
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_PERMISSIONS_TABLE,
            null,
            array(
                array("role_id", $role->id),
                array("permission_id", $permissionDef),
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

    public function getRolePermissions(string|object $role): array
    {
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return array();
            }
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_PERMISSIONS_TABLE,
            null,
            array(
                array("role_id", $role->id),
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
                } else {
                    $definition = $this->getPermissionDefinition($value->permission_id);

                    if ($definition === null) {
                        unset($new[$key]);
                    } else {
                        $value->definition = $definition;
                        $new[$key] = $value;
                    }
                }
            }
            return $new;
        }
    }

    public function addRolePermission(string|object $role, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add permissions to roles.");
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot add permission to a role with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getRolePosition($role);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        if ($this->getRolePermission($role, $permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission already given.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "team_id" => $role->team_id,
                "role_id" => $role->id,
                "permission_id" => $permissionDef,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to add permission.");
        }
    }

    public function removeRolePermission(string|object $role, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot remove permission from a role with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getRolePosition($role);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove permissions from roles.");
        }
        $permissionResult = $this->getRolePermission($role, $permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_ROLE_PERMISSIONS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $selfMemberID,
                "deletion_reason" => $reason
            ),
            array(
                array("id", $permissionResult->getObject()->id),
            ),
            null,
            1
        )) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to remove permission.");
        }
    }

    public function getRolePosition(string|object $role): ?int
    {
        $team = $this->findTeam($this->account)->getObject()?->id;

        if ($team === null) {
            return null;
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return null;
            }
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_POSITIONS_TABLE,
            null,
            array(
                array("role_id", $role->id),
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

    public function adjustRolePosition(string|object $role, int $position, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($this->account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if (is_string($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change positions of roles.");
        }
        $userPosition = $this->getPosition($this->account);
        $message = "Cannot change the position of a role with the same or higher position.";

        if ($userPosition === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getRolePosition($role);

        if ($otherPosition === null
            || $userPosition <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        if ($userPosition <= $position) {
            return new MethodReply(false, "Cannot change the position of a role to the your position or a higher position.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_ROLE_POSITIONS_TABLE,
            array(
                "team_id" => $role->team_id,
                "role_id" => $role->id,
                "position" => $position,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to change position.");
        }
    }

    // Separator

    private function getPermissionDefinition(int $id): ?object
    {
        $where = array(
            array("id", $id),
            array("deletion_date", null)
        );

        if ($this->additionalID === null) {
            $where[] = array("additional_id", null);
        } else {
            $where[] = null;
            $where[] = array("additional_id", "IS", null, 0);
            $where[] = array("additional_id", $this->additionalID);
            $where[] = null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSION_DEFINITIONS_TABLE,
            null,
            $where,
            null,
            1
        );
        return empty($query) ? null : $query[0];
    }

    public function addMemberPermission(Account $account, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot add permissions to yourself.");
        }
        $otherResult = $this->findTeam($this->account);
        $otherTeam = $otherResult->getObject()?->id;

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team !== $otherTeam) {
            return new MethodReply(false, "Cannot add permission to someone in another team.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add others permissions.");
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot add permission to someone with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getPosition($account);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if ($this->getMemberPermission($account, $permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission already given.");
        }
        if (sql_insert(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "team_id" => $team,
                "member_id" => $memberID,
                "permission_id" => $permissionDef,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true);
        } else {
            return new MethodReply(false, "Failed to add permission.");
        }
    }

    public function removeMemberPermission(Account $account, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam($account);
        $team = $result->getObject()?->id;

        if ($team === null) {
            return $result;
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot remove your own permissions.");
        }
        $otherResult = $this->findTeam($this->account);
        $otherTeam = $otherResult->getObject()?->id;

        if ($otherTeam === null) {
            return $otherResult;
        }
        if ($team !== $otherTeam) {
            return new MethodReply(false, "Cannot remove permission from someone in another team.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission not found.");
        }
        $position = $this->getPosition($this->account);
        $message = "Cannot remove permission from someone with the same or higher position.";

        if ($position === null) {
            return new MethodReply(false, $message);
        }
        $otherPosition = $this->getPosition($account);

        if ($otherPosition === null
            || $position <= $otherPosition) {
            return new MethodReply(false, $message);
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove others permissions.");
        }
        $permissionResult = $this->getMemberPermission($account, $permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $object = $permissionResult->getObject();

        if ($object === null) {
            return new MethodReply(false, "Permission object not found.");
        }
        $selfMemberID = $this->getMember($this->account)?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $selfMemberID,
                "deletion_reason" => $reason
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

    public function getMemberPermission(Account $account, int $permissionID): MethodReply
    {
        $result = $this->findTeam($account);
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

    public function getMemberPermissions(?Account $account = null): array
    {
        if ($account === null) {
            $account = $this->account;
        }
        $team = $this->findTeam($account)->getObject()?->id;

        if ($team === null) {
            return array();
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return array();
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return array();
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            $array = array();
            $date = get_current_date();

            foreach (self::PERMISSION_ALL as $value) {
                $object = new stdClass();
                $object->id = -random_number(9);
                $object->team_id = $team;
                $object->member_id = $memberID;
                $object->permission_id = $value;
                $object->creation_date = $date;
                $object->creation_reason = null;
                $object->created_by = null;
                $object->deletion_date = null;
                $object->deletion_reason = null;
                $object->deleted_by = null;
                $array[] = $object;
            }
            return $array;
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
            $roles = $this->getMemberRoles($account);

            if (!empty($roles)) {
                $new = array();

                foreach ($roles as $role) {
                    $rolePermissions = $this->getRolePermissions($role);

                    if (!empty($rolePermissions)) {
                        foreach ($rolePermissions as $value) {
                            if (!array_key_exists($value->permission_id, $new)) {
                                $new[$value->permission_id] = $value;
                            }
                        }
                    }
                }
                return $new;
            }
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
                } else {
                    $definition = $this->getPermissionDefinition($value->permission_id);

                    if ($definition === null) {
                        unset($new[$key]);
                    } else {
                        $value->definition = $definition;
                        $new[$key] = $value;
                    }
                }
            }
            $roles = $this->getMemberRoles($account);

            if (!empty($roles)) {
                foreach ($roles as $role) {
                    $rolePermissions = $this->getRolePermissions($role);

                    if (!empty($rolePermissions)) {
                        foreach ($rolePermissions as $value) {
                            if (!array_key_exists($value->permission_id, $new)) {
                                $new[$value->permission_id] = $value;
                            }
                        }
                    }
                }
            }
            return $new;
        }
    }

}
