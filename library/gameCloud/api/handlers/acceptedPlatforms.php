<?php

function get_accepted_platforms(?array     $select = null, int|string $id = null,
                                int|string $acceptedAccountID = null): array
{
    $hasID = $id !== null;
    $hasAcceptedAccountID = $acceptedAccountID !== null;
    return get_sql_query(
        GameCloudVariables::ACCEPTED_PLATFORMS_TABLE,
        $select,
        array(
            array("deletion_date", null),
            $hasID ? array("id", $id) : "",
            $hasAcceptedAccountID ? array("accepted_account_id", $acceptedAccountID) : ""
        ),
        null,
        $hasID || $hasAcceptedAccountID ? 1 : 0
    );
}
