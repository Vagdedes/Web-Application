<?php

class AcceptedAccount
{
    private ?object $object;

    public function __construct(?int $applicationID, int|string|null $id, string $name = null, bool $manual = true)
    {
        $query = get_sql_query(
            AccountVariables::ACCEPTED_ACCOUNTS_TABLE,
            null,
            array(
                $id !== null ? array("id", $id) : "",
                $name !== null ? array("name", $name) : "",
                $manual ? array("manual", "IS NOT", null) : "",
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