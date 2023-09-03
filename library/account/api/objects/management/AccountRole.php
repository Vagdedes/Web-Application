<?php

class AccountRole
{
    private int $id, $priority;
    private ?string $name, $prefix, $suffix;
    private bool $public;

    public function __construct($applicationID, $id, $checkDeletion = true)
    {
        global $roles_table;
        set_sql_cache("1 minute");
        $query = get_sql_query(
            $roles_table,
            array("name", "prefix", "suffix", "public", "priority"),
            array(
                array("id", $id),
                array("application_id", $applicationID),
                $checkDeletion ? array("deletion_date", null) : "",
            ),
            null,
            1
        );
        $this->id = $id;

        if (!empty($query)) {
            $query = $query[0];
            $this->name = $query->name;
            $this->prefix = $query->prefix;
            $this->suffix = $query->suffix;
            $this->public = $query->public !== null;
            $this->priority = $query->priority;
        } else {
            $this->name = null;
            $this->prefix = null;
            $this->suffix = null;
            $this->public = false;
            $this->priority = 0;
        }
    }

    public function exists(): bool
    {
        return $this->name !== null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function hasPermission($permission): bool
    {
        global $role_permissions_table;
        set_sql_cache("1 minute");
        return !empty(get_sql_query(
            $role_permissions_table,
            array("id"),
            array(
                array("role_id", $this->id),
                array("permission", $permission),
                array("deletion_date", null),
            ),
            null,
            1
        ));
    }

    public function getPermissions(): array
    {
        $cacheKey = array(self::class, $this->id);
        $cache = get_key_value_pair($cacheKey);

        if (is_array($cache)) {
            return $cache;
        } else {
            global $role_permissions_table;
            $array = array();
            $query = get_sql_query(
                $role_permissions_table,
                array("permission"),
                array(
                    array("role_id", $this->id),
                    array("deletion_date", null),
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
}
