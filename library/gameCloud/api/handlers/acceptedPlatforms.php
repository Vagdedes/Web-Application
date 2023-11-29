<?php

function get_accepted_platforms(?array     $select = null, int|string $id = null,
                                int|string $acceptedAccountID = null): array
{
    global $accepted_platforms_table;
    $hasID = $id !== null;
    $hasAcceptedAccountID = $acceptedAccountID !== null;
    set_sql_cache();
    return get_sql_query(
        $accepted_platforms_table,
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
