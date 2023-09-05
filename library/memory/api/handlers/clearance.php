<?php
$memory_trackers_query = get_sql_query(
    $memory_clearance_table,
    array("tracker", "array", "abstract_search"),
    array(
        array("creation", ">", time() - $memory_clearance_past),
    ),
    array(
        "DESC",
        "id"
    ),
    $memory_clearance_row_limit
);

if (!empty($memory_trackers_query)) {
    $segments = null;
    $memory_client_identifier = get_server_identifier();

    foreach ($memory_trackers_query as $row) {
        $array = @unserialize($row->array);

        if (is_array($array)) {
            if (empty(get_sql_query(
                $memory_clearance_tracking_table,
                array("tracker"),
                array(
                    array("tracker", $row->tracker),
                    array("identifier", $memory_client_identifier)
                ),
                null,
                1
            ))) {
                sql_insert(
                    $memory_clearance_tracking_table,
                    array(
                        "tracker" => $row->tracker,
                        "identifier" => $memory_client_identifier
                    )
                );
                if ($segments === null) {
                    $segments = get_memory_segment_ids();
                }
                clear_memory($array, $row->abstract_search !== null, $segments);
            }
        }
    }
}
