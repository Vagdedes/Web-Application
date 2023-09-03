<?php
$accepted_platforms_cache_time = "1 minute";

function get_accepted_platforms($select = null, $id = null, $acceptedAccountID = null): array
{
    global $accepted_platforms_table, $accepted_platforms_cache_time;
    $hasID = $id !== null;
    $hasAcceptedAccountID = $acceptedAccountID !== null;
    set_sql_cache($accepted_platforms_cache_time);
    return get_sql_query(
        $accepted_platforms_table,
        $select,
        array(
            array("deletion_date", null),
            $hasID ? array("id", $id) : "",
            $hasAcceptedAccountID ? array("accepted_account_id", $acceptedAccountID) : ""
        ),
        null,
        $hasID || $hasAcceptedAccountID ? 1 : null
    );
}
