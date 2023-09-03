<?php

class WebsiteModeration
{
    private string $name;
    private ?int $applicationID;

    public const ACCOUNT_BAN = "account_ban";

    public function __construct($applicationID, $name)
    {
        $this->name = $name;
        $this->applicationID = $applicationID;
    }

    public function getResult($select = null): MethodReply
    {
        global $moderations_table;
        $hasSelect = $select !== null;
        $key = is_numeric($this->name) ? "id" : "name";

        if ($this->applicationID === null) {
            $where = array(
                array($key, $this->name),
                array("deletion_date", null),
                array("application_id", null),
            );
        } else {
            $where = array(
                array($key, $this->name),
                array("deletion_date", null),
                null,
                array("application_id", "IS", null, 0), // Support default moderations for all applications
                array("application_id", $this->applicationID),
                null,
            );
        }
        set_sql_cache("1 minute");
        $query = get_sql_query(
            $moderations_table,
            $hasSelect ? $select : array("id"),
            $where,
            null,
            1
        );

        if (!empty($query)) {
            return new MethodReply(
                true,
                null,
                $hasSelect ? $query[0] : $query[0]->id
            );
        } else {
            return new MethodReply(
                false,
                "The '" . str_replace("_", "-", $this->name) . "' functionality is disabled or doesn't exist."
            );
        }
    }
}
