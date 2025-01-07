<?php

class AcceptedAccount
{
    private array $objects;

    public function __construct(
        ?int    $applicationID,
        ?int    $id,
        ?string $name = null,
        bool    $manual = true,
        ?array  $select = null
    )
    {
        $where = array(
            $id !== null ? array("id", $id) : "",
            $name !== null ? array("name", $name) : "",
            $manual ? array("manual", "IS NOT", null) : "",
            array("deletion_date", null),
        );
        if ($applicationID === null) {
            $where[] = array("application_id", null);
        } else {
            $where[] = null;
            $where[] = array("application_id", "IS", null, 0);
            $where[] = array("application_id", $applicationID);
            $where[] = null;
        }
        $this->objects = get_sql_query(
            AccountVariables::ACCEPTED_ACCOUNTS_TABLE,
            $select,
            $where
        );
    }

    public function exists(): bool
    {
        return !empty($this->objects);
    }

    public function getObjects(): array
    {
        return $this->objects;
    }
}