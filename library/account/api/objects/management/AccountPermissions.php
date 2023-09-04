<?php

class AccountPermissions
{
    private Account $account;
    private array $systemPermissions;
    private int $defaultRoleID;
    private $allPermissions, $rolePermissions, $lastUsed;

    public function __construct($account, $defaultRoleID = 10)
    {
        $this->account = $account;
        $this->allPermissions = null;
        $this->rolePermissions = null;
        $this->systemPermissions = array();
        $this->lastUsed = null;
        $this->defaultRoleID = $defaultRoleID;
    }

    public function getLastUsedPermission()
    {
        return $this->lastUsed;
    }

    public function getRoles(): array
    {
        $accountID = $this->account->getDetail("id");
        $cacheKey = array(self::class, $accountID, "roles");
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            return $cache;
        } else {
            global $account_roles_table;
            $array = array();
            $query = get_sql_query(
                $account_roles_table,
                array("role_id"),
                array(
                    array("account_id", $accountID),
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
            set_key_value_pair($cacheKey, $array, "1 minute");
            return $array;
        }
    }

    public function hasRole($role): bool
    {
        if ($role == $this->defaultRoleID) {
            return true;
        } else {
            global $account_roles_table;
            set_sql_cache("1 minute");
            return !empty(get_sql_query(
                $account_roles_table,
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
        if ($this->allPermissions === null) {
            $this->allPermissions = array_merge(
                $this->getRolePermissions(),
                $this->getGivenPermissions()
            );
            $this->allPermissions = array_merge(
                $this->allPermissions,
                $this->getSystemPermissions()
            );
        }
        return $this->allPermissions;
    }

    public function getRolePermissions(): array
    {
        if ($this->rolePermissions === null) {
            $roles = $this->getRoles();
            $this->rolePermissions = array();

            if (!empty($roles)) {
                foreach ($roles as $role) {
                    foreach ($role->getPermissions() as $permission) {
                        $this->rolePermissions[] = $permission;
                    }
                }
            }
        }
        return $this->rolePermissions;
    }

    public function getGivenPermissions(): array
    {
        $accountID = $this->account->getDetail("id");
        $cacheKey = array(self::class, $accountID, "given");
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            return $cache;
        } else {
            global $account_permissions_table;
            $array = array();
            $query = get_sql_query(
                $account_permissions_table,
                array("permission"),
                array(
                    array("account_id", $accountID),
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
            set_key_value_pair($cacheKey, $array, "1 minute");
            return $array;
        }
    }

    public function getSystemPermissions(): array
    {
        return $this->systemPermissions;
    }

    public function addSystemPermission($permission)
    {
        if (is_array($permission)) {
            $this->systemPermissions = array_merge(
                $this->systemPermissions,
                $permission
            );
        } else {
            $this->systemPermissions[] = $permission;
        }
    }

    public function hasPermission($permission, $store = false, ?Account $accountAgainst = null): bool
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
