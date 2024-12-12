<?php

class AccountRole
{
    private ?string $name, $prefix, $suffix, $creationDate, $creationReason;
    private ?int $id, $priority, $createdBy;
    private bool $public;

    public function __construct(?int $applicationID, int|string $id, bool $checkDeletion = true)
    {
        $query = get_sql_query(
            AccountVariables::ROLES_TABLE,
            array("id", "name", "prefix", "suffix", "public", "priority", "creation_date", "creation_reason", "created_by"),
            array(
                (is_numeric($id) ? array("id", $id) : array("name", $id)),
                array("application_id", $applicationID),
                $checkDeletion ? array("deletion_date", null) : "",
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $this->id = $query->id;
            $this->name = $query->name;
            $this->prefix = $query->prefix;
            $this->suffix = $query->suffix;
            $this->public = $query->public !== null;
            $this->priority = $query->priority;
            $this->creationDate = $query->creation_date;
            $this->creationReason = $query->creation_reason;
            $this->createdBy = $query->created_by;
        } else {
            $this->id = null;
            $this->name = null;
            $this->prefix = null;
            $this->suffix = null;
            $this->public = false;
            $this->priority = null;
            $this->creationDate = null;
            $this->creationReason = null;
            $this->createdBy = null;
        }
    }

    public function exists(): bool
    {
        return $this->name !== null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
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

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    public function getCreationReason(): ?string
    {
        return $this->creationReason;
    }

    public function getPermission(string $permission): MethodReply
    {
        $query = get_sql_query(
            AccountVariables::ROLE_PERMISSIONS_TABLE,
            array("id", "creation_date", "creation_reason", "created_by"),
            array(
                array("role_id", $this->id),
                array("permission", $permission),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            null,
            1
        );

        if (empty($query)) {
            return new MethodReply(false);
        } else {
            return new MethodReply(true, null, $query[0]);
        }
    }

    public function getPermissions(): array
    {
        $array = array();
        $query = get_sql_query(
            AccountVariables::ROLE_PERMISSIONS_TABLE,
            array("permission", "creation_date", "creation_reason", "created_by"),
            array(
                array("role_id", $this->id),
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

}
