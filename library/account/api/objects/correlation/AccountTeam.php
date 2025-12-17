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
        PERMISSION_RESTORE_TEAM_ROLES = 65,

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
        PERMISSION_ADJUST_TEAM_PERMISSIONS = 34,
        PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS = 14,
        PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS = 15,

        // Grouping
        PERMISSION_ADJUST_TEAM_MEMBER_ROLES = 13;

    private const
        MAX_TEAMS = 100,
        MAX_MEMBERS = 1000,
        MAX_PERMISSIONS = 500,
        MAX_ROLES = 500;

    private Account $account;
    private ?int $additionalID;
    private ?object $forcedTeam;
    private array $forcedTeamResult;
    private bool $permissions;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->additionalID = self::DEFAULT_ADDITIONAL_ID;
        $this->forcedTeam = null;
        $this->permissions = true;
        $this->forcedTeamResult = array();
    }

    // Separator

    public function togglePermissions(bool $b): void
    {
        $this->permissions = $b;
    }

    public function setTeamAdditionalID(int $additionalID): void
    {
        $this->additionalID = $additionalID;
    }

    public function setForcedTeam(object $team): void
    {
        $this->forcedTeam = $team;
        $this->forcedTeam->title = $this->getTeamTitle();
        $this->forcedTeamResult = array();
    }

    public function clearCache(): void
    {
        $this->forcedTeam = null;
        $this->forcedTeamResult = array();
    }

    // Separator

    public function createTeam(string $title, ?string $description, ?string $reason = null): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Member with ID '"
                . $this->account->getDetail("id", "unknown")
                . "' not found.");
        }
        if (strlen($title) === 0) {
            return new MethodReply(false, "Title cannot be empty.");
        }
        if (strlen($title) > 128) {
            return new MethodReply(false, "Title cannot be longer than 128 characters.");
        }
        $result = $this->findTeam($title);

        if ($result->isPositiveOutcome()) {
            return new MethodReply(false, "Team with the title '" . $title . "' already exists.");
        }
        $date = get_current_date();

        if (sql_insert(
            AccountVariables::TEAM_TABLE,
            array(
                "additional_id" => $this->additionalID,
                "creation_date" => $date,
                "created_by_account" => $this->account->getDetail("id"),
                "creation_reason" => $reason
            ))) {
            $insertID = get_sql_last_insert_id();

            if ($insertID === null) {
                return new MethodReply(false, "Failed to find created team.");
            } else {
                if (sql_insert(
                    AccountVariables::TEAM_MEMBERS_TABLE,
                    array(
                        "team_id" => $insertID,
                        "account_id" => $this->account->getDetail("id"),
                        "creation_date" => $date,
                    ))) {
                    if (!sql_insert(
                        AccountVariables::TEAM_NAME_CHANGES,
                        array(
                            "team_id" => $insertID,
                            "name" => $title,
                            "creation_date" => $date,
                        )
                    )) {
                        return new MethodReply(false, "Failed to add team title.");
                    }
                    if (!sql_insert(
                        AccountVariables::TEAM_NAME_CHANGES,
                        array(
                            "team_id" => $insertID,
                            "name" => $description,
                            "creation_date" => $date,
                            "description" => true
                        )
                    )) {
                        return new MethodReply(false, "Failed to add team description.");
                    }
                    return new MethodReply(true, "Team created as '" . $title . "'.");
                } else {
                    return new MethodReply(false, "Failed to add member to the team.");
                }
            }
        } else {
            return new MethodReply(false, "Failed to create team.");
        }
    }

    public function getAllTeams(): array
    {
        return get_sql_query(
            AccountVariables::TEAM_TABLE,
            null,
            array(
                array("additional_id", $this->additionalID),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            )
        );
    }

    public function findTeams(Account $account = null): array
    {
        if ($account === null) {
            $account = $this->account;
        }
        if ($account->exists()) {
            $query = get_sql_query(
                AccountVariables::TEAM_MEMBERS_TABLE,
                array("team_id"),
                array(
                    array("account_id", $account->getDetail("id")),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                self::MAX_TEAMS
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
                        $result[$subQuery[0]->id] = $subQuery[0];
                    }
                }
                return $result;
            }
        }
        return array();
    }

    public function findTeam(
        Account|string|int|null $reference = null,
        int|object|null         $checkAgainst = null): MethodReply
    {
        if ($this->forcedTeam !== null
            && $checkAgainst === null
            && ($reference === null
                || $reference instanceof Account
                || is_numeric($reference) && $reference == $this->forcedTeam->id
                || is_string($reference) && $reference === $this->forcedTeam->title)) {
            if (isset($this->forcedTeam->id)) {
                if (sizeof($this->forcedTeamResult) === 2
                    && microtime(true) - $this->forcedTeamResult[1] < 10.0) {
                    return new MethodReply(true, "Forced team found successfully.", $this->forcedTeamResult[0]);
                }
                $query = $this->forcedTeam->id <= 0
                    ? null
                    : get_sql_query(
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
                    return new MethodReply(false, "Forced team with ID '" . $this->forcedTeam->id . "' not found.");
                } else {
                    $this->forcedTeamResult = array($query[0], microtime(true));
                    return new MethodReply(true, "Forced team found successfully.", $query[0]);
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
                return new MethodReply(false, "Member with ID '"
                    . $reference->getDetail("id", "unknown")
                    . "' not found.");
            }
            $query = get_sql_query(
                AccountVariables::TEAM_MEMBERS_TABLE,
                array("team_id"),
                array(
                    array("account_id", $reference->getDetail("id")),
                    array("deletion_date", null),
                    ($checkAgainst !== null
                        ? array("team_id", is_numeric($checkAgainst) ? $checkAgainst : $checkAgainst->id)
                        : "")
                ),
                array(
                    "DESC",
                    "id"
                ),
                self::MAX_TEAMS
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
                        $subQuery = $subQuery[0];
                        $this->setForcedTeam($subQuery);
                        return new MethodReply(true, "Team successfully found.", $subQuery);
                    }
                }
            }
            return new MethodReply(false, "Team not found.");
        } else if (is_numeric($reference)) {
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
                return new MethodReply(false, "Team with ID '" . $reference . "' not found.");
            } else {
                return new MethodReply(true, "Team found successfully.", $query[0]);
            }
        } else {
            $query = get_sql_query(
                AccountVariables::TEAM_NAME_CHANGES,
                array("team_id"),
                array(
                    array("name", $reference),
                    array("description", false)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (empty($query)) {
                return new MethodReply(false, "Team with name '" . $reference . "' not found.");
            }
            $subQuery = get_sql_query(
                AccountVariables::TEAM_TABLE,
                null,
                array(
                    array("id", $query[0]->team_id),
                    array("additional_id", $this->additionalID),
                    array("deletion_date", null)
                ),
                null,
                1
            );

            if (empty($subQuery)) {
                return new MethodReply(false, "Team with ID '" . $query[0]->team_id . "' not found.");
            } else {
                return new MethodReply(true, "Team found successfully.", $subQuery[0]);
            }
        }
    }

    public function deleteTeam(?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
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
                array("id", $result->getObject()->id)
            ),
            null,
            1
        )) {
            $this->clearCache();
            return new MethodReply(true, "Team deleted successfully.");
        } else {
            return new MethodReply(false, "Failed to delete team.");
        }
    }

    public function transferTeamOwnership(Account $account, ?string $reason = null, bool $automatic = false): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $otherResult = $this->findTeam($account, $result->getObject()->id);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "Member is not in this team.");
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found or user not in a team.");
        }
        if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
            return new MethodReply(false, "You must be the owner of this team to transfer it.");
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            return new MethodReply(false, "You already own this team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member with ID '"
                . $account->getDetail("id", "unknown")
                . "' not found.");
        }
        $team = $result->getObject()->id;

        if (!sql_insert(
            AccountVariables::TEAM_OWNERS_TABLE,
            array(
                "team_id" => $team,
                "member_id" => $memberID,
                "creation_date" => get_current_date(),
                "created_by" => $owner->id,
                "creation_reason" => $reason,
                "automatic" => $automatic
            )
        )) {
            return new MethodReply(false, "Failed to transfer team ownership to the member '" . $this->getMemberIdentity($account) . "'.");
        }
        return new MethodReply(true, "Team ownership transferred to the member '" . $this->getMemberIdentity($account) . "' successfully.");
    }

    // Separator

    private function getTeamName(bool $description): ?string
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_NAME_CHANGES,
            array("name"),
            array(
                array("team_id", $result->getObject()->id),
                array("description", $description),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
        return empty($query)
            ? null
            : $query[0]->name;
    }

    public function getTeamTitle(): ?string
    {
        return $this->getTeamName(false);
    }

    public function getTeamDescription(): ?string
    {
        return $this->getTeamName(true);
    }

    public function updateTeamTitle(string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (strlen($name) === 0) {
            return new MethodReply(false, "Title cannot be empty.");
        }
        if (strlen($name) > 128) {
            return new MethodReply(false, "Title cannot be longer than 128 characters.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_NAME)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team title.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if ($this->getTeamTitle() === $name) {
            return new MethodReply(false, "Team title is already set to this value.");
        }
        if (sql_insert(
            AccountVariables::TEAM_NAME_CHANGES,
            array(
                "team_id" => $result->getObject()->id,
                "name" => $name,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true, "Team title updated to '" . $name . "'.");
        } else {
            return new MethodReply(false, "Failed to update team title to '" . $name . "'.");
        }
    }

    public function updateTeamDescription(?string $description, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if ($description !== null) {
            if (strlen($description) === 0) {
                return new MethodReply(false, "Description cannot be empty.");
            }
            if (strlen($description) > 512) {
                return new MethodReply(false, "Description cannot be longer than 512 characters.");
            }
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_DESCRIPTION)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team description.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if ($this->getTeamDescription() === $description) {
            return new MethodReply(false, "Team description is already set to this value.");
        }
        if (sql_insert(
            AccountVariables::TEAM_NAME_CHANGES,
            array(
                "team_id" => $result->getObject()->id,
                "name" => $description,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason,
                "description" => true
            ))) {
            return new MethodReply(true, "Team description updated to '" . $description . "'.");
        } else {
            return new MethodReply(false, "Failed to update team description to '" . $description . "'.");
        }
    }

    // Separator

    public function leaveTeam(?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $memberCount = sizeof($this->getMembers());

        if ($memberCount === 1) {
            return $this->deleteTeam();
        } else {
            $memberID = $this->getMember()?->id;

            if ($memberID === null) {
                return new MethodReply(false, "Member not found.");
            }
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "Owner not found.");
            }
            if ($owner->account->getDetail("id") === $this->account->getDetail("id")) {
                $newOwner = $this->getCoOwner($owner);

                if ($newOwner === null) {
                    return new MethodReply(false, "New owner not found.");
                }
                $adjust = $this->transferTeamOwnership(
                    $newOwner->account,
                    null,
                    true
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
                $this->clearCache();
                return new MethodReply(true, "You left the team '" . $this->getTeamTitle() . "'.");
            } else {
                return new MethodReply(false, "Failed to leave the team '" . $this->getTeamTitle() . "'.");
            }
        }
    }

    private function getMemberIdentity(?Account $account = null): string
    {
        if ($account === null) {
            $account = $this->account;
        }
        $middleName = $account->getDetail(AccountActions::MIDDLE_NAME, null);
        $lastName = $account->getDetail(AccountActions::LAST_NAME, null);
        $lastIdentity = trim(
            $account->getDetail(AccountActions::FIRST_NAME, "")
            . ($middleName !== null ? " " . $middleName : "")
            . ($lastName !== null ? " " . $lastName : "")
        );

        if (empty($lastIdentity)) {
            $lastIdentity = $account->getDetail("email_address", "");
        }
        return $lastIdentity;
    }

    public function addMember(Account $account, ?string $reason = null, bool $multiple = true): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADD_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add members to the team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot add yourself to a team.");
        }
        if ($account->getDetail("application_id") !== $this->account->getDetail("application_id")) {
            return new MethodReply(false, "Members must be from the same application.");
        }
        $team = $result->getObject()->id;

        if ($multiple) {
            $otherResult = $this->findTeam($account, $team);

            if ($otherResult->isPositiveOutcome()) {
                return new MethodReply(false, "Member is already in this team.");
            }
        } else {
            $otherResult = $this->findTeam($account);

            if ($otherResult->isPositiveOutcome()) {
                return new MethodReply(false, "Member is already in a team.");
            }
        }
        $rookie = $this->getRookie();

        if ($rookie === null) {
            return new MethodReply(false, "Rookie not found.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $date = get_current_date();

        if (sql_insert(
            AccountVariables::TEAM_MEMBERS_TABLE,
            array(
                "team_id" => $team,
                "account_id" => $account->getDetail("id"),
                "creation_date" => $date,
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true, "Member '" . $this->getMemberIdentity($account) . "' added to the team.");
        } else {
            return new MethodReply(false, "Failed to add member '" . $this->getMemberIdentity($account) . "' to the team.");
        }
    }

    public function removeMember(Account $account, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $otherResult = $this->findTeam($account, $result->getObject()->id);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "The other member is not in this team.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_REMOVE_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove members from the team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot remove yourself from a team.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member with ID '"
                . $account->getDetail("id", "unknown")
                . "' not found.");
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
            return new MethodReply(true, "Member '" . $this->getMemberIdentity($account) . "' removed from the team successfully.");
        } else {
            return new MethodReply(false, "Failed to remove member '" . $this->getMemberIdentity($account) . "' from the team.");
        }
    }

    public function getMembers(): array
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
                array("deletion_date", null)
            ),
            null,
            self::MAX_MEMBERS
        );

        if (empty($query)) {
            return array();
        } else {
            $new = array();

            foreach ($query as $value) {
                if (!array_key_exists($value->account_id, $new)) {
                    $value->account = $this->account->getNew($value->account_id);
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

    public function getMember(Account|int|null $account = null): ?object
    {
        if ($account === null) {
            $account = $this->account;
        }
        $result = $this->findTeam($account);

        if (!$result->isPositiveOutcome()) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
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
            $query->account = $this->account->getNew($query->account_id);

            if ($query->account->exists()) {
                return $query;
            } else {
                return null;
            }
        }
    }

    // Separator

    public function getOwner(bool $idOnly = false): object|int|string|null
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_OWNERS_TABLE,
            array("member_id"),
            array(
                array("team_id", $result->getObject()->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            if ($idOnly) {
                return $result->getObject()?->created_by_account;
            }
            $owner = $this->account->getNew(
                $result->getObject()?->created_by_account
            );
            return $owner->exists()
                ? $this->getMember($owner)
                : null;
        } else {
            $query = get_sql_query(
                AccountVariables::TEAM_MEMBERS_TABLE,
                null,
                array(
                    array("id", $query[0]->member_id)
                ),
                null,
                1
            );

            if (empty($query)) {
                return null;
            }
            if ($idOnly) {
                return $query[0]->account_id;
            }
            $query = $query[0];
            $query->account = $this->account->getNew($query->account_id);
            return $query;
        }
    }

    private function getCoOwner(object $owner): ?object
    {
        $members = $this->getMembers();

        if (!empty($members)) {
            $max = null;
            $coOwner = null;

            foreach ($members as $member) {
                if ($member->account->getDetail("id") !== $owner->account->getDetail("id")) {
                    $position = $this->getPosition($member->account);

                    if ($max === null || $position > $max) {
                        $max = $position;
                        $coOwner = $member;
                    }
                }
            }
            return $coOwner;
        } else {
            return null;
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

    public function getPosition(?Account $account = null, bool $setOwnerToMax = true): int
    {
        if ($account === null) {
            $account = $this->account;
        }
        $result = $this->findTeam($account);

        if (!$result->isPositiveOutcome()) {
            global $min_32bit_Integer;
            return $min_32bit_Integer;
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            global $min_32bit_Integer;
            return $min_32bit_Integer;
        }
        if ($setOwnerToMax
            && $owner->account->getDetail("id") === $account->getDetail("id")) {
            global $max_32bit_Integer;
            return $max_32bit_Integer;
        }
        global $min_32bit_Integer;
        $member = $this->getMember($account)?->id;

        if ($member === null) {
            return $min_32bit_Integer;
        }
        $max = $member->last_position ?? $min_32bit_Integer;
        $roles = $this->getMemberRoles($account);

        if (empty($roles)) {
            return $max;
        } else {
            foreach ($roles as $role) {
                $position = $this->getRolePosition($role);

                if ($position > $max) {
                    $max = $position;
                }
            }
            return $max;
        }
    }

    public function adjustMemberPosition(Account $account, int $position, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $team = $result->getObject()->id;
        $otherResult = $this->findTeam($account, $team);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "The other member is not in this team.");
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found.");
        }
        $owner = $owner->account->getDetail("id") === $this->account->getDetail("id");

        if (!$owner
            && $account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot change your own hierarchical position in the team.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change others positions.");
        }
        $userPosition = $this->getPosition();

        if (!$owner
            && $userPosition <= $position) {
            return new MethodReply(false, "Cannot change the hierarchical position to the your or a higher hierarchical position level.");
        }
        if (!$owner
            && $userPosition <= $this->getPosition($account)) {
            return new MethodReply(false, "Cannot change the hierarchical position of a member with the same or higher hierarchical position.");
        }
        global $max_32bit_Integer;

        if ($position >= $max_32bit_Integer) {
            return new MethodReply(false, "Cannot change the hierarchical position to the highest possible level.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member with ID '"
                . $account->getDetail("id", "unknown")
                . "' not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_MEMBERS_TABLE,
            array(
                "last_position" => $position
            ),
            array(
                array("id", $memberID)
            ),
            null,
            1
        )) {
            if (sql_insert(
                AccountVariables::TEAM_MEMBER_POSITIONS_TABLE,
                array(
                    "team_id" => $team,
                    "member_id" => $memberID,
                    "position" => $position,
                    "creation_date" => get_current_date(),
                    "created_by" => $selfMemberID,
                    "creation_reason" => $reason
                ))) {
                return new MethodReply(true, "Team hierarchical position of member has changed.");
            } else {
                return new MethodReply(false, "Failed to change hierarchical position history of member.");
            }
        } else {
            return new MethodReply(false, "Failed to change hierarchical position of member.");
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
                } else {
                    $position = $this->getRolePosition($loopObject);
                }
                if ($max === null || $position > $max) {
                    $max = $position;
                }
            }
        } else if ($against instanceof Account) {
            $max = $this->getPosition($against);
        } else {
            $max = $this->getRolePosition($against);
        }
        return $reference instanceof Account
            ? $this->adjustMemberPosition($reference, $max + ($above ? 1 : -1), $reason)
            : $this->adjustRolePosition($reference, $max + ($above ? 1 : -1), $reason);
    }

    // Separator

    public function updateRoleTitle(string|int|object $role, string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_ROLE_NAMES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team names.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "Cannot change a role's name with the same or higher hierarchical position.");
        }
        $selfMemberID = $this->getMember()?->id;

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
                return new MethodReply(true, "Team role's title updated to '" . $name . "'.");
            } else {
                return new MethodReply(false, "Failed to fully update the team role's title.");
            }
        } else {
            return new MethodReply(false, "Failed to update the team role's title to '" . $name . "'.");
        }
    }

    public function updateRoleDescription(string|int|object $role, string $description, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_ROLE_DESCRIPTIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change team names.");
        }
        if (is_string($role) || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "Cannot change a role's name with the same or higher hierarchical position.");
        }
        $selfMemberID = $this->getMember()?->id;

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
                return new MethodReply(true, "Team role's description updated to '" . $description . "'.");
            } else {
                return new MethodReply(false, "Failed to fully update the team role's description.");
            }
        } else {
            return new MethodReply(false, "Failed to update the team role's description to '" . $description . "'.");
        }
    }

    public function createRole(string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CREATE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to create team roles.");
        }
        if (strlen($name) === 0) {
            return new MethodReply(false, "Role name cannot be empty.");
        }
        $role = $this->getRole($name);

        if ($role !== null) {
            return new MethodReply(false, "Role with the name '" . $name . "' already exists.");
        }
        $rookie = $this->getRookieRole();

        if ($rookie === null) {
            global $min_32bit_Integer;
            $rookie = $min_32bit_Integer;
        } else {
            $rookie = $this->getRolePosition($rookie);
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $date = get_current_date();
        $team = $result->getObject()->id;

        if (sql_insert(
            AccountVariables::TEAM_ROLES_TABLE,
            array(
                "team_id" => $team,
                "title" => $name,
                "creation_date" => $date,
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            $insertID = get_sql_last_insert_id();

            if ($insertID === null) {
                return new MethodReply(false, "new Role ID not found.");
            }
            if (sql_insert(
                AccountVariables::TEAM_ROLE_POSITIONS_TABLE,
                array(
                    "team_id" => $team,
                    "role_id" => $insertID,
                    "position" => $rookie,
                    "creation_date" => $date,
                    "created_by" => $selfMemberID
                ))) {
                return new MethodReply(true, "Role '" . $name . "' created in the team.");
            } else {
                return new MethodReply(false, "Failed to set role hierarchical position in the team.");
            }
        } else {
            return new MethodReply(false, "Failed to create role in the team.");
        }
    }

    public function deleteRole(string|int|object $role, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_DELETE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to delete team roles.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "Cannot delete a role with the same or higher hierarchical position.");
        }
        $selfMemberID = $this->getMember()?->id;

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
            return new MethodReply(true, "Role '" . $role->title . "' deleted from the team.");
        } else {
            return new MethodReply(false, "Failed to delete role '" . $role->title . "' from the team.");
        }
    }

    public function restoreRole(string|int|object $role): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_RESTORE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to restore deleted team roles.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role, true);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role, true)) {
            return new MethodReply(false, "Cannot restore a deleted role with the same or higher hierarchical position.");
        }
        $roleSimilar = $this->getRole($role->name);

        if ($roleSimilar !== null) {
            return new MethodReply(false, "Cannot restore role with name '" . $role->name . "' as it is used for another role.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            array(
                "deletion_date" => null,
                "deleted_by" => null,
                "deletion_reason" => null
            ),
            array(
                array("id", $role->id)
            ),
            null,
            1
        )) {
            return new MethodReply(true, "Deleted role '" . $role->title . "' successfully restored in the team.");
        } else {
            return new MethodReply(false, "Failed to restore deleted role '" . $role->title . "' in the team.");
        }
    }

    public function getRoleMembers(): array
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_MEMBERS_TABLE,
            array("account_id"),
            array(
                array("team_id", $result->getObject()->id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            self::MAX_MEMBERS
        );

        if (!empty($query)) {
            $array = array();

            foreach ($query as $row) {
                if (!in_array($row->account_id, $array)) {
                    $array[] = $row->account_id;
                }
            }
            return $array;
        }
        return array();
    }

    public function getMemberRoles(?Account $account = null): array
    {
        if ($account === null) {
            $account = $this->account;
        }
        $result = $this->findTeam($account);

        if (!$result->isPositiveOutcome()) {
            return array();
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return array();
        }
        $team = $result->getObject()->id;
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_MEMBERS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            self::MAX_ROLES
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

    public function getRole(string|int $reference, bool $deleted = false): ?object
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return null;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
                is_numeric($reference) ? array("id", $reference) : array("LOWER(title)", strtolower($reference)),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        }
        return $query[0];
    }

    public function getTeamRoles(bool $deleted = false): array
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return array();
        }
        return get_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
                $deleted
                    ? array("deletion_date", "IS NOT", null)
                    : array("deletion_date", null)
            ),
            null,
            self::MAX_ROLES
        );
    }

    public function setRole(Account $account, string|int|object $role, bool $trueFalse, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change roles of members.");
        }
        $position = $this->getPosition();

        if ($position <= $this->getPosition($account)) {
            return new MethodReply(false, "Cannot change the role of a member with the same or higher hierarchical position.");
        }
        $team = $result->getObject()->id;
        $otherResult = $this->findTeam($account, $team);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if ($position <= $this->getRolePosition($role)) {
            if ($trueFalse) {
                return new MethodReply(false, "Cannot give a role with the same or higher hierarchical position.");
            } else {
                return new MethodReply(false, "Cannot remove a role with the same or higher hierarchical position.");
            }
        }
        $roles = $this->getMemberRoles($account);

        if ($trueFalse) {
            foreach ($roles as $value) {
                if ($value->id === $role->id) {
                    return new MethodReply(false, "Role already given to the member '" . $this->getMemberIdentity($account) . "'.");
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
                return new MethodReply(true, "Role '" . $role->title . "' given to the member '" . $this->getMemberIdentity($account) . "'.");
            } else {
                return new MethodReply(false, "Failed to give role '" . $role->title . "' to the member '" . $this->getMemberIdentity($account) . "'.");
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
                            return new MethodReply(true, "Role '" . $role->title . "' removed from the member '" . $this->getMemberIdentity($account) . "'.");
                        } else {
                            return new MethodReply(false, "Failed to remove role '" . $role->title . "' from the member '" . $this->getMemberIdentity($account) . "'.");
                        }
                    }
                }
            }
            return new MethodReply(false, "Role not given to the member '" . $this->getMemberIdentity($account) . "'.");
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

    public function getRolePermission(string|int|object $role, int $permissionID): MethodReply
    {
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_ROLE_PERMISSIONS_TABLE,
            null,
            array(
                array("role_id", $role->id),
                array("permission_id", $permissionDef)
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
                ? new MethodReply(true, "Permission already given.", $query)
                : new MethodReply(false, "Permission given and removed.");
        }
    }

    public function getRolePermissions(string|int|object $role): array
    {
        if (is_string($role) || is_int($role)) {
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
            ),
            self::MAX_PERMISSIONS
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

    public function addRolePermission(string|int|object $role, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (is_string($role) || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add permissions to roles.");
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "Cannot add permission to a role with the same or higher hierarchical position.");
        }
        if ($this->getRolePermission($role, $permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission already given.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (sql_insert(
            AccountVariables::TEAM_ROLE_PERMISSIONS_TABLE,
            array(
                "team_id" => $role->team_id,
                "role_id" => $role->id,
                "permission_id" => $permissionDef,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true, "Permission added to the role '" . $role->title . "'.");
        } else {
            return new MethodReply(false, "Failed to add permission to the role '" . $role->title . "'.");
        }
    }

    public function removeRolePermission(string|int|object $role, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "Cannot remove permission from a role with the same or higher hierarchical position.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove permissions from roles.");
        }
        $permissionResult = $this->getRolePermission($role, $permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $selfMemberID = $this->getMember()?->id;

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
                array("id", $permissionResult->getObject()->id)
            ),
            null,
            1
        )) {
            return new MethodReply(true, "Permission removed from the role '" . $role->title . "'.");
        } else {
            return new MethodReply(false, "Failed to remove permission from the role '" . $role->title . "'.");
        }
    }

    public function getRolePosition(string|int|object $role, bool $deleted = false): int
    {
        if (is_object($role)) {
            global $min_32bit_Integer;
            return $role->last_position ?? $min_32bit_Integer;
        }
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            global $min_32bit_Integer;
            return $min_32bit_Integer;
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role, $deleted);

            if ($role === null) {
                global $min_32bit_Integer;
                return $min_32bit_Integer;
            }
        }
        global $min_32bit_Integer;
        return $role->last_position ?? $min_32bit_Integer;
    }

    public function adjustRolePosition(string|int|object $role, int $position, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "Role not found.");
            }
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to change positions of roles.");
        }
        global $max_32bit_Integer;

        if ($position >= $max_32bit_Integer) {
            return new MethodReply(false, "Cannot change the hierarchical position to the highest possible level.");
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found.");
        }
        $owner = $owner->account->getDetail("id") === $this->account->getDetail("id");
        $userPosition = $this->getPosition();

        if (!$owner
            && $userPosition <= $this->getRolePosition($role)) {
            return new MethodReply(false, "Cannot change the hierarchical position of a role with the same or higher hierarchical position.");
        }
        if (!$owner
            && $userPosition <= $position) {
            return new MethodReply(false, "Cannot change the hierarchical position of a role to the your hierarchical position or a higher hierarchical position.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_ROLES_TABLE,
            array(
                "last_position" => $position
            ),
            array(
                array("id", $role->id)
            ),
            null,
            1
        )) {
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
                return new MethodReply(true, "Hierarchical position changed successfully for the role '" . $role->title . "'.");
            } else {
                return new MethodReply(false, "Failed to change the hierarchical position history of the role '" . $role->title . "'.");
            }
        } else {
            return new MethodReply(false, "Failed to change the hierarchical position of the role '" . $role->title . "'.");
        }
    }

    // Separator

    public function getPermissionDefinitions(): array
    {
        $where = array(
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
        return get_sql_query(
            AccountVariables::TEAM_PERMISSION_DEFINITIONS_TABLE,
            null,
            $where,
            null,
            self::MAX_PERMISSIONS
        );
    }

    public function getPermissionDefinition(int|string $id): ?object
    {
        $where = array(
            is_numeric($id) ? array("id", $id) : array("name", $id),
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

    public function addMemberPermission(?Account $account, int $permissionID, ?string $reason = null): MethodReply
    {
        if ($account === null) {
            $account = $this->account;
        }
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot add permissions to yourself.");
        }
        $team = $result->getObject()->id;
        $otherResult = $this->findTeam($account, $team);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "The other member is not in this team.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add others permissions.");
        }
        if ($this->getPosition() <= $this->getPosition($account)) {
            return new MethodReply(false, "Cannot add permission to someone with the same or higher hierarchical position.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member with ID '"
                . $account->getDetail("id", "unknown")
                . "' not found.");
        }
        if ($this->getMemberPermission($account, $permissionDef, false, false)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission already given.");
        }
        if (sql_insert(
            AccountVariables::TEAM_MEMBER_PERMISSIONS_TABLE,
            array(
                "team_id" => $team,
                "member_id" => $memberID,
                "permission_id" => $permissionDef,
                "creation_date" => get_current_date(),
                "created_by" => $selfMemberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true, "Permission added to the member '" . $this->getMemberIdentity($account) . "'.");
        } else {
            return new MethodReply(false, "Failed to add permission to the member '" . $this->getMemberIdentity($account) . "'.");
        }
    }

    public function removeMemberPermission(Account $account, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You cannot remove your own permissions.");
        }
        $otherResult = $this->findTeam($account, $result->getObject()->id);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "The other member is not in this team.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        if ($this->getPosition() <= $this->getPosition($account)) {
            return new MethodReply(false, "Cannot remove permission from someone with the same or higher hierarchical position.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove others permissions.");
        }
        $permissionResult = $this->getMemberPermission($account, $permissionDef, false, false);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $object = $permissionResult->getObject();

        if ($object === null) {
            return new MethodReply(false, "Permission object not found.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_MEMBER_PERMISSIONS_TABLE,
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
            return new MethodReply(true, "Permission removed from the member '" . $this->getMemberIdentity($account) . "'.");
        } else {
            return new MethodReply(false, "Failed to remove permission from the member '" . $this->getMemberIdentity($account) . "'.");
        }
    }

    public function getMemberPermission(
        ?Account $account,
        int      $permissionID,
        bool     $checkTeam = true,
        bool     $checkRoles = true
    ): MethodReply
    {
        if ($account === null) {
            $account = $this->account;
        }
        $result = $this->findTeam($account);

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "Owner not found.");
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            return new MethodReply(true, "Owner has all permissions.");
        }
        if (!$this->permissions) {
            return new MethodReply(true, "Permission system disabled.");
        }
        if ($checkTeam && $this->getTeamPermission($permissionID)->isPositiveOutcome()) {
            return new MethodReply(true, "Permission given to all members.");
        }
        if ($checkRoles) {
            $roles = $this->getMemberRoles($account);

            if (!empty($roles)) {
                foreach ($roles as $role) {
                    $rolePermission = $this->getRolePermission($role, $permissionID);

                    if ($rolePermission->isPositiveOutcome()) {
                        return new MethodReply(true, "Permission given to one of user's roles.");
                    }
                }
            }
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Member not found.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBER_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
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
                ? new MethodReply(true, "Permission given.", $query)
                : new MethodReply(false, "Permission given and removed.");
        }
    }

    public function getMemberPermissions(?Account $account = null): array
    {
        if ($account === null) {
            $account = $this->account;
        }
        $result = $this->findTeam($account);

        if (!$result->isPositiveOutcome()) {
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
        $team = $result->getObject()->id;

        if ($owner->account->getDetail("id") === $account->getDetail("id")
            || !$this->permissions) {
            $array = array();
            $date = get_current_date();
            $definitions = $this->getPermissionDefinitions();

            if (!empty($definitions)) {
                foreach ($definitions as $definition) {
                    $object = new stdClass();
                    $object->id = -random_number(9);
                    $object->team_id = $team;
                    $object->member_id = $memberID;
                    $object->permission_id = $definition->id;
                    $object->creation_date = $date;
                    $object->creation_reason = null;
                    $object->created_by = null;
                    $object->deletion_date = null;
                    $object->deletion_reason = null;
                    $object->deleted_by = null;
                    $object->definition = $definition;
                    $array[] = $object;
                }
            }
            return $array;
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBER_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $team),
                array("member_id", $memberID),
            ),
            array(
                "DESC",
                "id"
            ),
            self::MAX_PERMISSIONS
        );
        $new = array();

        if (!empty($query)) {
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
        }
        $teamPermissions = $this->getTeamPermissions();

        if (!empty($teamPermissions)) {
            foreach ($teamPermissions as $value) {
                if (!array_key_exists($value->permission_id, $new)) {
                    $new[$value->permission_id] = $value;
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

    // Separator

    public function addTeamPermission(int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $team = $result->getObject()->id;
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to add default team permissions.");
        }
        $memberID = $this->getMember()?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Executor member not found.");
        }
        if ($this->getTeamPermission($permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "Permission already given.");
        }
        if (sql_insert(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "team_id" => $team,
                "permission_id" => $permissionDef,
                "creation_date" => get_current_date(),
                "created_by" => $memberID,
                "creation_reason" => $reason
            ))) {
            return new MethodReply(true, "Permission added to the whole team.");
        } else {
            return new MethodReply(false, "Failed to add permission to the whole team.");
        }
    }

    public function removeTeamPermission(int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "' not found.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_PERMISSIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "Missing permission to remove default team permissions.");
        }
        $permissionResult = $this->getTeamPermission($permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "Permission not given.");
        }
        $object = $permissionResult->getObject();

        if ($object === null) {
            return new MethodReply(false, "Permission object not found.");
        }
        $memberID = $this->getMember()?->id;

        if ($memberID === null) {
            return new MethodReply(false, "Executor member with ID '" . $memberID . "' not found.");
        }
        if (set_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $memberID,
                "deletion_reason" => $reason
            ),
            array(
                array("id", $object->id),
            ),
            null,
            1
        )) {
            return new MethodReply(true, "Permission removed from the whole team.");
        } else {
            return new MethodReply(false, "Failed to remove permission from the whole team.");
        }
    }

    public function getTeamPermission(int $permissionID): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "Permission with ID '" . $permissionID . "'not found.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
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
                ? new MethodReply(true, "Permission given.", $query)
                : new MethodReply(false, "Permission given and removed.");
        }
    }

    public function getTeamPermissions(): array
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return array();
        }
        $query = get_sql_query(
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $result->getObject()?->id),
            ),
            array(
                "DESC",
                "id"
            ),
            self::MAX_PERMISSIONS
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

}
