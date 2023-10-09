<?php

class AcceptedAccount
{
    private ?object $object;

    public function __construct($applicationID, $id, $name = null)
    {
        global $accepted_accounts_table;
        set_sql_cache();
        $query = get_sql_query(
            $accepted_accounts_table,
            null,
            array(
                $id !== null ? array("id", $id) : "",
                $name !== null ? array("name", $name) : "",
                array("deletion_date", null),
                array("application_id", $applicationID)
            ),
            null,
            1
        );

        if (empty($query)) {
            $this->object = null;
        } else {
            $this->object = $query[0];
        }
    }

    public function exists(): bool
    {
        return $this->object !== null;
    }

    public function getObject(): ?object
    {
        return $this->object;
    }
}