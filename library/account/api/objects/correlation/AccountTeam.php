<?php

class AccountTeam
{

    private const MEMORY_CACHE_SECONDS = 5.0;

    public const
        DEFAULT_ADDITIONAL_ID = null,
        IDEALISTIC_OFFICE_ADDITIONAL_ID = 0,

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
            return new MethodReply(false, "We couldn't find your account (ID: "
                . $this->account->getDetail("id", "unknown")
                . ").");
        }
        if (strlen($title) === 0) {
            return new MethodReply(false, "Please provide a team title.");
        }
        if (strlen($title) > 128) {
            return new MethodReply(false, "The team title is too long. Please keep it under 128 characters.");
        }
        $result = $this->findTeam($title);

        if ($result->isPositiveOutcome()) {
            return new MethodReply(false, "A team named '" . $title . "' already exists. Please choose a different name.");
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
                return new MethodReply(false, "We created the team, but couldn't retrieve its new ID.");
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
                        return new MethodReply(false, "We couldn't save the new team's title.");
                    }
                    if ($description !== null
                        && !sql_insert(
                            AccountVariables::TEAM_NAME_CHANGES,
                            array(
                                "team_id" => $insertID,
                                "name" => $description,
                                "creation_date" => $date,
                                "description" => true
                            )
                        )) {
                        return new MethodReply(false, "We couldn't save the new team's description.");
                    }
                    return new MethodReply(true, "Your team '" . $title . "' has been successfully created!");
                } else {
                    return new MethodReply(false, "We couldn't add you as a member to the new team.");
                }
            }
        } else {
            return new MethodReply(false, "We ran into an issue while trying to create the team.");
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
                    && microtime(true) - $this->forcedTeamResult[1] <= self::MEMORY_CACHE_SECONDS) {
                    return new MethodReply(true, "We found the requested team.", $this->forcedTeamResult[0]);
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
                    return new MethodReply(false, "We couldn't find the requested team (ID: " . $this->forcedTeam->id . ").");
                } else {
                    $this->forcedTeamResult = array($query[0], microtime(true));
                    return new MethodReply(true, "We found the requested team.", $query[0]);
                }
            } else {
                return new MethodReply(false, "The requested team's ID is missing.");
            }
        }
        if ($reference === null) {
            $reference = $this->account;
        }
        if ($reference instanceof Account) {
            if (!$reference->exists()) {
                return new MethodReply(false, "We couldn't find the member account (ID: "
                    . $reference->getDetail("id", "unknown")
                    . ").");
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
                        return new MethodReply(true, "Team located successfully.", $subQuery);
                    }
                }
            }
            return new MethodReply(false, "We couldn't find that team.");
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
                return new MethodReply(false, "We couldn't find a team with the ID '" . $reference . "'.");
            } else {
                return new MethodReply(true, "Team located successfully.", $query[0]);
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
                return new MethodReply(false, "We couldn't find a team named '" . $reference . "'.");
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
                return new MethodReply(false, "We located the team name, but couldn't find the team data (ID: " . $query[0]->team_id . ").");
            } else {
                return new MethodReply(true, "Team located successfully.", $subQuery[0]);
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
            return new MethodReply(false, "We couldn't identify the owner of this team.");
        }
        if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
            return new MethodReply(false, "Only the team owner has permission to delete this team.");
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
            return new MethodReply(true, "The team has been deleted successfully.");
        } else {
            return new MethodReply(false, "We ran into an issue while trying to delete the team.");
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
            return new MethodReply(false, "That member isn't currently part of this team.");
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "We couldn't verify the current owner of the team.");
        }
        if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
            return new MethodReply(false, "Only the current team owner can transfer ownership.");
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            return new MethodReply(false, "You are already the owner of this team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't locate the account profile for the new owner (ID: "
                . $account->getDetail("id", "unknown")
                . ").");
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
            return new MethodReply(false, "We failed to transfer ownership to '" . $this->getMemberIdentity($account) . "'.");
        }
        return new MethodReply(true, "You've successfully transferred team ownership to '" . $this->getMemberIdentity($account) . "'.");
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
            return new MethodReply(false, "Please provide a valid team title.");
        }
        if (strlen($name) > 128) {
            return new MethodReply(false, "The team title is too long. Please keep it under 128 characters.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_NAME)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to change the team's title.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        if ($this->getTeamTitle() === $name) {
            return new MethodReply(false, "The team is already using that title.");
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
            return new MethodReply(true, "The team title has been updated to '" . $name . "'.");
        } else {
            return new MethodReply(false, "We ran into an issue while trying to update the title to '" . $name . "'.");
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
                return new MethodReply(false, "Please provide a team description.");
            }
            if (strlen($description) > 512) {
                return new MethodReply(false, "The description is too long. Please keep it under 512 characters.");
            }
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_DESCRIPTION)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to change the team's description.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        if ($this->getTeamDescription() === $description) {
            return new MethodReply(false, "The team is already using that description.");
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
            return new MethodReply(true, "The team description has been successfully updated.");
        } else {
            return new MethodReply(false, "We ran into an issue while trying to update the description.");
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
                return new MethodReply(false, "We couldn't verify your membership in this team.");
            }
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't identify the current team owner.");
            }
            if ($owner->account->getDetail("id") === $this->account->getDetail("id")) {
                $newOwner = $this->getCoOwner($owner);

                if ($newOwner === null) {
                    return new MethodReply(false, "As the owner, you must transfer ownership to someone else before leaving.");
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
                return new MethodReply(true, "You have successfully left the team '" . $this->getTeamTitle() . "'.");
            } else {
                return new MethodReply(false, "We encountered a problem while trying to remove you from the team.");
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
            return new MethodReply(false, "You don't have permission to add new members to this team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't add yourself to the team.");
        }
        if ($account->getDetail("application_id") !== $this->account->getDetail("application_id")) {
            return new MethodReply(false, "Team members must belong to the same application.");
        }
        $team = $result->getObject()->id;

        if ($multiple) {
            $otherResult = $this->findTeam($account, $team);

            if ($otherResult->isPositiveOutcome()) {
                return new MethodReply(false, "That member is already a part of this team.");
            }
        } else {
            $otherResult = $this->findTeam($account);

            if ($otherResult->isPositiveOutcome()) {
                return new MethodReply(false, "That member is already in a team.");
            }
        }
        $rookie = $this->getRookie();

        if ($rookie === null) {
            return new MethodReply(false, "We couldn't determine the base rank for new members.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
            return new MethodReply(true, "'" . $this->getMemberIdentity($account) . "' has been added to the team.");
        } else {
            return new MethodReply(false, "We ran into an issue adding '" . $this->getMemberIdentity($account) . "' to the team.");
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
            return new MethodReply(false, "That user is not a member of this team.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_REMOVE_TEAM_MEMBERS)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to remove members from this team.");
        }
        if ($account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't remove yourself using this action. Please use the 'Leave Team' option instead.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't locate the profile for member ID '"
                . $account->getDetail("id", "unknown")
                . "'.");
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
            return new MethodReply(true, "'" . $this->getMemberIdentity($account) . "' has been removed from the team.");
        } else {
            return new MethodReply(false, "We couldn't remove '" . $this->getMemberIdentity($account) . "' from the team.");
        }
    }

    public function getSortedLevels(): array
    {
        $members = $this->getMembers();
        $levels = [];

        if (!empty($members)) {
            foreach ($members as $member) {
                $levels[] = $this->getPosition($member->account);
            }
        }
        $roles = $this->getTeamRoles();

        if (!empty($roles)) {
            foreach ($roles as $role) {
                $levels[] = $this->getRolePosition($role);
            }
        }
        $uniqueLevels = array_unique($levels);
        rsort($uniqueLevels, SORT_NUMERIC);
        $hierarchy = [];

        foreach ($uniqueLevels as $index => $levelValue) {
            $hierarchy[$levelValue] = $index + 1;
        }
        return $hierarchy;
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

                    if ($value->account->exists()) {
                        $new[$value->account_id] = $value;
                    }
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
            return 0;
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return 0;
        }
        if ($setOwnerToMax
            && $owner->account->getDetail("id") === $account->getDetail("id")) {
            global $max_32bit_Integer;
            return $max_32bit_Integer;
        }
        $member = $this->getMember($account)?->id;

        if ($member === null) {
            return 0;
        }
        $max = $member->last_position ?? 0;
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
            return new MethodReply(false, "That user is not a member of this team.");
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "We couldn't identify the current team owner.");
        }
        $owner = $owner->account->getDetail("id") === $this->account->getDetail("id");

        if (!$owner
            && $account->getDetail("id") === $this->account->getDetail("id")) {
            return new MethodReply(false, "You can't alter your own rank within the team.");
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to adjust member ranks.");
        }
        $userPosition = $this->getPosition();

        if (!$owner
            && $userPosition <= $position) {
            return new MethodReply(false, "You can't promote a member to a rank equal to or higher than your own.");
        }
        if (!$owner
            && $userPosition <= $this->getPosition($account)) {
            return new MethodReply(false, "You can't modify the rank of a member who is at or above your own level.");
        }
        global $max_32bit_Integer;

        if ($position >= $max_32bit_Integer) {
            return new MethodReply(false, "You cannot set a member's rank to the absolute maximum value.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't locate the profile for member ID '"
                . $account->getDetail("id", "unknown")
                . "'.");
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
                return new MethodReply(true, "The member's rank has been successfully updated.");
            } else {
                return new MethodReply(false, "We updated the rank but failed to save the history record.");
            }
        } else {
            return new MethodReply(false, "We couldn't update the member's rank.");
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
            return new MethodReply(false, "You don't have permission to rename team roles.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "You can't rename a role that is ranked equal to or higher than your own.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
                return new MethodReply(true, "The role title has been updated to '" . $name . "'.");
            } else {
                return new MethodReply(false, "We saved the history but failed to fully update the role title.");
            }
        } else {
            return new MethodReply(false, "We encountered an issue updating the role title to '" . $name . "'.");
        }
    }

    public function updateRoleDescription(string|int|object $role, string $description, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CHANGE_TEAM_ROLE_DESCRIPTIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to edit role descriptions.");
        }
        if (is_string($role) || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "You can't edit the description of a role that is ranked equal to or higher than your own.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
                return new MethodReply(true, "The role description has been successfully updated.");
            } else {
                return new MethodReply(false, "We saved the history but failed to fully update the role description.");
            }
        } else {
            return new MethodReply(false, "We ran into a problem updating the role description.");
        }
    }

    public function createRole(string $name, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_CREATE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to create new team roles.");
        }
        if (strlen($name) === 0) {
            return new MethodReply(false, "Please provide a name for the role.");
        }
        $role = $this->getRole($name);

        if ($role !== null) {
            return new MethodReply(false, "A role named '" . $name . "' already exists.");
        }
        $rookie = $this->getRookieRole();

        if ($rookie === null) {
            $rookie = 0;
        } else {
            $rookie = $this->getRolePosition($rookie);
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
                return new MethodReply(false, "Role created, but we couldn't retrieve its new ID.");
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
                return new MethodReply(true, "The role '" . $name . "' has been successfully created.");
            } else {
                return new MethodReply(false, "The role was created, but we failed to assign its initial rank.");
            }
        } else {
            return new MethodReply(false, "We ran into an issue while trying to create the role.");
        }
    }

    public function deleteRole(string|int|object $role, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_DELETE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to delete team roles.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "You can't delete a role that is ranked equal to or higher than your own.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
            return new MethodReply(true, "The role '" . $role->title . "' has been deleted.");
        } else {
            return new MethodReply(false, "We couldn't delete the role '" . $role->title . "'.");
        }
    }

    public function restoreRole(string|int|object $role): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_RESTORE_TEAM_ROLES)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to restore deleted team roles.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role, true);

            if ($role === null) {
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($this->getPosition() <= $this->getRolePosition($role, true)) {
            return new MethodReply(false, "You can't restore a role that would be ranked equal to or higher than your own.");
        }
        $roleSimilar = $this->getRole($role->name);

        if ($roleSimilar !== null) {
            return new MethodReply(false, "You can't restore the role '" . $role->name . "' because another active role is now using that name.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
            return new MethodReply(true, "The role '" . $role->title . "' has been successfully restored.");
        } else {
            return new MethodReply(false, "We ran into an issue restoring the role '" . $role->title . "'.");
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
            return new MethodReply(false, "You don't have permission to modify member roles.");
        }
        $position = $this->getPosition();

        if ($this->account->getDetail("id") !== $account->getDetail("id")
            && $position <= $this->getPosition($account)) {
            return new MethodReply(false, "You can't modify the roles of a member ranked equal to or higher than your own.");
        }
        $team = $result->getObject()->id;
        $otherResult = $this->findTeam($account, $team);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "That user is not a member of this team.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't locate the member's profile.");
        }
        if (is_string($role)
            || is_int($role)) {
            $role = $this->getRole($role);

            if ($role === null) {
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($position <= $this->getRolePosition($role)) {
            if ($trueFalse) {
                return new MethodReply(false, "You can't assign a role that is ranked equal to or higher than your own.");
            } else {
                return new MethodReply(false, "You can't remove a role that is ranked equal to or higher than your own.");
            }
        }
        $roles = $this->getMemberRoles($account);

        if ($trueFalse) {
            foreach ($roles as $value) {
                if ($value->id === $role->id) {
                    return new MethodReply(false, "'" . $this->getMemberIdentity($account) . "' already has this role.");
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
                return new MethodReply(true, "'" . $this->getMemberIdentity($account) . "' has been granted the role '" . $role->title . "'.");
            } else {
                return new MethodReply(false, "We failed to assign the role '" . $role->title . "' to '" . $this->getMemberIdentity($account) . "'.");
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
                            return new MethodReply(true, "The role '" . $role->title . "' has been removed from '" . $this->getMemberIdentity($account) . "'.");
                        } else {
                            return new MethodReply(false, "We couldn't remove the role '" . $role->title . "' from '" . $this->getMemberIdentity($account) . "'.");
                        }
                    }
                }
            }
            return new MethodReply(false, "'" . $this->getMemberIdentity($account) . "' does not currently have this role.");
        }
    }

    public function getRookieRole(): ?object
    {
        $roles = $this->getTeamRoles();

        if (empty($roles)) {
            return null;
        } else {
            $min = null;
            $rookie = null;

            foreach ($roles as $role) {
                $position = $role->last_position ?? 0;

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
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
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
            return new MethodReply(false, "This permission has not been granted.");
        } else {
            $query = $query[0];
            return $query->deletion_date === null
                ? new MethodReply(true, "This permission is already active.", $query)
                : new MethodReply(false, "This permission was previously granted and then removed.");
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
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($permissionID === self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't verify the current owner of the team.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "Only the team owner can assign permissions to manage other permissions.");
            }
        }
        $outcome = $this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS);

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "You can't add permissions to a role ranked equal to or higher than your own.");
        }
        if ($this->getRolePermission($role, $permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "This permission has already been granted to the role.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
            return new MethodReply(true, "Permission has been added to the role '" . $role->title . "'.");
        } else {
            return new MethodReply(false, "We encountered an error adding the permission to the role '" . $role->title . "'.");
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
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if ($permissionID === self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't verify the current owner of the team.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "Only the team owner can remove permissions that manage other permissions.");
            }
        }
        $outcome = $this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS);

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        if ($this->getPosition() <= $this->getRolePosition($role)) {
            return new MethodReply(false, "You can't remove permissions from a role ranked equal to or higher than your own.");
        }
        $permissionResult = $this->getRolePermission($role, $permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "That permission is not currently assigned to the role.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
            return new MethodReply(true, "Permission has been removed from the role '" . $role->title . "'.");
        } else {
            return new MethodReply(false, "We encountered an error removing the permission from the role '" . $role->title . "'.");
        }
    }

    public function getRolePosition(string|int|object|null $role, bool $deleted = false, bool $redundant = true): int
    {
        if ($role === null) {
            return 0;
        }
        if (!is_object($role)) {
            $role = $this->getRole($role, $deleted);
        }
        if (is_object($role)
            && isset($role->last_position)) {
            return $role->last_position;
        }
        return $redundant
            ? $this->getRolePosition($this->getRookieRole(), $deleted, false)
            : 0;
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
                return new MethodReply(false, "We couldn't find that role.");
            }
        }
        if (!$this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_ROLE_POSITIONS)->isPositiveOutcome()) {
            return new MethodReply(false, "You don't have permission to change role ranks.");
        }
        global $max_32bit_Integer;

        if ($position >= $max_32bit_Integer) {
            return new MethodReply(false, "You can't set a role's rank to the absolute maximum value.");
        }
        $owner = $this->getOwner();

        if ($owner === null) {
            return new MethodReply(false, "We couldn't verify the current owner of the team.");
        }
        $owner = $owner->account->getDetail("id") === $this->account->getDetail("id");
        $userPosition = $this->getPosition();

        if (!$owner
            && $userPosition <= $this->getRolePosition($role)) {
            return new MethodReply(false, "You can't change the rank of a role that is ranked equal to or higher than your own.");
        }
        if (!$owner
            && $userPosition <= $position) {
            return new MethodReply(false, "You can't promote a role to a rank equal to or higher than your own.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
                return new MethodReply(true, "The rank for the role '" . $role->title . "' has been successfully updated.");
            } else {
                return new MethodReply(false, "We updated the rank but failed to save the history record for the role '" . $role->title . "'.");
            }
        } else {
            return new MethodReply(false, "We ran into an error trying to update the rank for the role '" . $role->title . "'.");
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
        $outcome = $this->getMemberPermission(
            $this->account,
            self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS
        );

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        if ($permissionID === self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't verify the current owner of the team.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "Only the team owner can assign permissions to manage other permissions.");
            }
        }
        $team = $result->getObject()->id;
        $otherResult = $this->findTeam($account, $team);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "That user is not a member of this team.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        if ($this->account->getDetail("id") !== $account->getDetail("id")
            && $this->getPosition() <= $this->getPosition($account)) {
            return new MethodReply(false, "You can't assign permissions to someone ranked equal to or higher than yourself.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't locate the profile for member ID '"
                . $account->getDetail("id", "unknown")
                . "'.");
        }
        if ($this->getMemberPermission($account, $permissionDef, false, false)->isPositiveOutcome()) {
            return new MethodReply(false, "This permission has already been assigned.");
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
            return new MethodReply(true, "Permission has been added for '" . $this->getMemberIdentity($account) . "'.");
        } else {
            return new MethodReply(false, "We encountered an error adding the permission for '" . $this->getMemberIdentity($account) . "'.");
        }
    }

    public function removeMemberPermission(Account $account, int $permissionID, ?string $reason = null): MethodReply
    {
        $result = $this->findTeam();

        if (!$result->isPositiveOutcome()) {
            return $result;
        }
        $outcome = $this->getMemberPermission(
            $this->account,
            self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS
        );

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        if ($permissionID === self::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't verify the current owner of the team.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "Only the team owner can remove permissions that manage other permissions.");
            }
        }
        $otherResult = $this->findTeam($account, $result->getObject()->id);

        if (!$otherResult->isPositiveOutcome()) {
            return new MethodReply(false, "That user is not a member of this team.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        if ($this->account->getDetail("id") !== $account->getDetail("id")
            && $this->getPosition() <= $this->getPosition($account)) {
            return new MethodReply(false, "You can't remove permissions from someone ranked equal to or higher than yourself.");
        }
        $permissionResult = $this->getMemberPermission($account, $permissionDef, false, false);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "This permission is not currently assigned to the member.");
        }
        $object = $permissionResult->getObject();

        if ($object === null) {
            return new MethodReply(false, "We couldn't retrieve the permission data.");
        }
        $selfMemberID = $this->getMember()?->id;

        if ($selfMemberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
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
            return new MethodReply(true, "Permission has been removed from '" . $this->getMemberIdentity($account) . "'.");
        } else {
            return new MethodReply(false, "We ran into an error removing the permission from '" . $this->getMemberIdentity($account) . "'.");
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
            return new MethodReply(false, "We couldn't identify the current team owner.");
        }
        if ($owner->account->getDetail("id") === $account->getDetail("id")) {
            return new MethodReply(true, "As the owner, you already hold all permissions.");
        }
        if (!$this->permissions) {
            return new MethodReply(true, "The permission system is currently disabled.");
        }
        $permissionDef = $this->getPermissionDefinition($permissionID);

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        if ($checkTeam && $this->getTeamPermission($permissionID)->isPositiveOutcome()) {
            return new MethodReply(true, "The permission '" . $permissionDef->name . "' is granted globally to the whole team.");
        }
        if ($checkRoles) {
            $roles = $this->getMemberRoles($account);

            if (!empty($roles)) {
                foreach ($roles as $role) {
                    $rolePermission = $this->getRolePermission($role, $permissionID);

                    if ($rolePermission->isPositiveOutcome()) {
                        return new MethodReply(true, "The permission '" . $permissionDef->name . "' is granted via one of the user's roles.");
                    }
                }
            }
        }
        $memberID = $this->getMember($account)?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't find the member profile.");
        }
        $query = get_sql_query(
            AccountVariables::TEAM_MEMBER_PERMISSIONS_TABLE,
            null,
            array(
                array("team_id", $result->getObject()->id),
                array("member_id", $memberID),
                array("permission_id", $permissionDef->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return new MethodReply(false, "The permission '" . $permissionDef->name . "' is not granted.");
        } else {
            $query = $query[0];
            return $query->deletion_date === null
                ? new MethodReply(true, "The permission '" . $permissionDef->name . "' is currently active.", $query)
                : new MethodReply(false, "The permission '" . $permissionDef->name . "' was previously assigned but has been removed.");
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

        if ($permissionID === self::PERMISSION_ADJUST_TEAM_PERMISSIONS) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't verify the current owner of the team.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "Only the team owner can grant the team permission to manage other permissions.");
            }
        }
        $outcome = $this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_PERMISSIONS);

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        $permissionDef = $this->getPermissionDefinition($permissionID)?->id;

        if ($permissionDef === null) {
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        $memberID = $this->getMember()?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't verify your membership in this team.");
        }
        if ($this->getTeamPermission($permissionDef)->isPositiveOutcome()) {
            return new MethodReply(false, "This permission is already assigned to the entire team.");
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
            return new MethodReply(true, "The permission has been granted to the entire team.");
        } else {
            return new MethodReply(false, "We encountered an error granting the permission to the team.");
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
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
        }
        if ($permissionID === self::PERMISSION_ADJUST_TEAM_PERMISSIONS) {
            $owner = $this->getOwner();

            if ($owner === null) {
                return new MethodReply(false, "We couldn't verify the current owner of the team.");
            }
            if ($owner->account->getDetail("id") !== $this->account->getDetail("id")) {
                return new MethodReply(false, "Only the team owner can revoke the team permission to manage other permissions.");
            }
        }
        $outcome = $this->getMemberPermission($this->account, self::PERMISSION_ADJUST_TEAM_PERMISSIONS);

        if (!$outcome->isPositiveOutcome()) {
            return $outcome;
        }
        $permissionResult = $this->getTeamPermission($permissionDef);

        if (!$permissionResult->isPositiveOutcome()) {
            return new MethodReply(false, "This permission is not currently assigned to the team.");
        }
        $object = $permissionResult->getObject();

        if ($object === null) {
            return new MethodReply(false, "We couldn't retrieve the permission data.");
        }
        $memberID = $this->getMember()?->id;

        if ($memberID === null) {
            return new MethodReply(false, "We couldn't verify your membership (ID: " . $memberID . ").");
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
            return new MethodReply(true, "The permission has been revoked from the team.");
        } else {
            return new MethodReply(false, "We encountered an error removing the permission from the team.");
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
            return new MethodReply(false, "We couldn't locate permission ID '" . $permissionID . "'.");
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
            return new MethodReply(false, "This permission is not granted to the team.");
        } else {
            $query = $query[0];
            return $query->deletion_date === null
                ? new MethodReply(true, "The permission is active.", $query)
                : new MethodReply(false, "The permission was previously granted but has been removed.");
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