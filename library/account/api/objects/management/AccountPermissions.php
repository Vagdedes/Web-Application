<?php

class AccountPermissions
{
    private Account $account;
    private array $systemPermissions;
    private int $defaultRoleID;
    private string|array|null $lastUsed;

    public function __construct(Account $account, int $defaultRoleID = 10)
    {
        $this->account = $account;
        $this->systemPermissions = array();
        $this->lastUsed = null;
        $this->defaultRoleID = $defaultRoleID;
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
        $array[] = new AccountRole(
            $this->account->getDetail("application_id"),
            $this->defaultRoleID,
            false
        );
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
