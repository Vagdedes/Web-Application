<?php

class AccountPermissions
{
    private const DEFAULT_ROLE_ID_PER_APPLICATION_ID = array(
        Account::DEFAULT_APPLICATION_ID => 10
    );

    private Account $account;
    private array $systemPermissions;
    private ?int $defaultRoleID;
    private string|array|null $lastUsed;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->systemPermissions = array();
        $this->lastUsed = null;
        $this->defaultRoleID = self::DEFAULT_ROLE_ID_PER_APPLICATION_ID[$account->getDetail("application_id")] ?? null;
    }

    public function setDefaultRoleID(?int $roleID): void
    {
        $this->defaultRoleID = $roleID;
    }

    public function getLastUsedPermission(): string|array|null
    {
        return $this->lastUsed;
    }

    public function getRoles(): array
    {
        if (!$this->account->exists()) {
            return array();
        }
        $array = array();
        $query = get_sql_query(
            AccountVariables::ACCOUNT_ROLES_TABLE,
            array("role_id"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("creation_date", "IS NOT", null),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $role = new AccountRole(
                    $this->account->getDetail("application_id"),
                    $row->role_id
                );

                if ($role->exists()) {
                    $array[] = $role;
                }
            }
        }
        if ($this->defaultRoleID !== null) {
            $array[] = new AccountRole(
                $this->account->getDetail("application_id"),
                $this->defaultRoleID,
                false
            );
        }
        return $array;
    }

    public function hasRole(int|string $role): bool
    {
        if (!$this->account->exists()) {
            return false;
        }
        if ($role == $this->defaultRoleID) {
            return true;
        } else {
            return !empty(get_sql_query(
                AccountVariables::ACCOUNT_ROLES_TABLE,
                array("id"),
                array(
                    array("account_id", $accountID),
                    array("role_id", $role),
                    array("creation_date", "IS NOT", null),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                null,
                1
            ));
        }
    }

    public function getAllPermissions(): array
    {
        return array_merge(
            $this->getRolePermissions(),
            $this->getGivenPermissions(),
            $this->getSystemPermissions()
        );
    }

    public function getRolePermissions(): array
    {
        $array = array();
        $roles = $this->getRoles();

        if (!empty($roles)) {
            foreach ($roles as $role) {
                foreach ($role->getPermissions() as $permission) {
                    $array[] = $permission;
                }
            }
        }
        return $array;
    }

    public function getGivenPermissions(): array
    {
        if (!$this->account->exists()) {
            return array();
        }
        $array = array();
        $query = get_sql_query(
            AccountVariables::ACCOUNT_PERMISSIONS_TABLE,
            array("permission"),
            array(
                array("account_id", $this->account->getDetail("id")),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $array[] = $row->permission;
            }
        }
        return $array;
    }

    public function getSystemPermissions(): array
    {
        return $this->systemPermissions;
    }

    public function addSystemPermission(string|array $permission): void
    {
        if ($this->account->exists()) {
            if (is_array($permission)) {
                foreach ($permission as $key) {
                    $this->addSystemPermission($key);
                }
            } else if (!in_array($permission, $this->systemPermissions)) {
                $this->systemPermissions[] = $permission;
            }
        }
    }

    public function addPermission(
        Account         $account,
        string|array    $permission,
        int|string|null $expiration = null,
        string          $reason = null
    ): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Executor account not found.");
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::ACCOUNT_PERMISSIONS_TABLE,
            array("id"),
            array(
                array("permission", $permission),
                array("account_id", $date),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (empty($query)) {
            if (sql_insert(
                AccountVariables::ACCOUNT_PERMISSIONS_TABLE,
                array(
                    "account_id" => $account->getDetail("id"),
                    "permission" => $permission,
                    "creation_date" => $date,
                    "created_by" => $this->account->getDetail("id"),
                    "creation_reason" => $reason,
                    "expiration_date" => $expiration !== null ? get_future_date($expiration) : null
                )
            )) {
                return new MethodReply(true, "Permission given.");
            } else {
                return new MethodReply(false, "Failed to give permission.");
            }
        } else {
            return new MethodReply(false, "Permission already given.");
        }
    }

    public function removePermission(Account $account, string|array $permission, string $reason = null): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Executor account not found.");
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::ACCOUNT_PERMISSIONS_TABLE,
            array("id"),
            array(
                array("permission", $permission),
                array("account_id", $account->getDetail("id")),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (empty($query)) {
            return new MethodReply(false, "Permission not found.");
        } else {
            if (set_sql_query(
                AccountVariables::ACCOUNT_PERMISSIONS_TABLE,
                array(
                    "deletion_date" => $date,
                    "deleted_by" => $this->account->getDetail("id"),
                    "deletion_reason" => $reason
                ),
                array(
                    array("id", $query[0]->id)
                ),
                null,
                1
            )) {
                return new MethodReply(true, "Permission removed.");
            } else {
                return new MethodReply(false, "Failed to remove permission.");
            }
        }
    }

    public function addRolePermission(Account $account, string $role, string|array $permission): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Executor account not found.");
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        return new MethodReply(false);
    }

    public function removeRolePermission(Account $account, string $role, string|array $permission): MethodReply
    {
        if (!$this->account->exists()) {
            return new MethodReply(false, "Executor account not found.");
        }
        if (!$account->exists()) {
            return new MethodReply(false, "Account not found.");
        }
        return new MethodReply(false);
    }

    public function hasPermission(string|array $permission, bool $store = false, ?Account $accountAgainst = null): bool
    {
        if ($accountAgainst !== null
            && $accountAgainst->getDetail("id") !== $this->account->getDetail("id")
            && $this->getPriority() <= $accountAgainst->getPermissions()->getPriority()) {
            return false;
        }
        $this->lastUsed = $permission;

        if (is_array($permission)) {
            $permission[] = "*";
        } else {
            $permission = array($permission, "*");
        }
        foreach ($permission as $key) {
            if (in_array($key, $this->getAllPermissions())) {
                if ($store) {
                    $this->account->getHistory()->addLastUsedPermission();
                }
                return true;
            }
        }
        return false;
    }

    public function isAdministrator(): bool
    {
        return in_array("*", $this->getAllPermissions());
    }

    public function getPriority(): int
    {
        $roles = $this->getRoles();

        if (!empty($roles)) {
            $max = 0;

            foreach ($roles as $role) {
                $priority = $role->getPriority();

                if ($priority > $max) {
                    $max = $priority;
                }
            }
            return $max;
        }
        return 0;
    }

}
