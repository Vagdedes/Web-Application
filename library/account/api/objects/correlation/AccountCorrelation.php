<?php

class AccountCorrelation
{
    private Account $account;


    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getType(int|string $id): MethodReply
    {
        global $statistic_types_table;
        $query = get_sql_query(
            $statistic_types_table,
            array("id", "name", "description", "creation_date"),
            array(
                array("id", $id),
                array("application_id", $this->account->getDetail("application_id")),
                array("deletion_date", null)
            ),
            null,
            1
        );
        return empty($query)
            ? new MethodReply(false)
            : new MethodReply(true, null, $query[0]);
    }

    // Separator

    public function createInstant()
    {

    }

    public function deleteInstant()
    {

    }

    public function getAllInstant()
    {

    }

    public function getSentInstant()
    {

    }

    public function getReceivedInstant()
    {

    }

    public function getInstant()
    {

    }

    // Separator

    public function createRequest()
    {

    }

    public function acceptRequest()
    {

    }

    public function denyRequest()
    {

    }

    public function deleteRequest()
    {

    }

    public function getAllRequests()
    {

    }

    public function getSentRequests()
    {

    }

    public function getReceivedRequests()
    {

    }

    public function getRequest()
    {

    }
}