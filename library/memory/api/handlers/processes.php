<?php

function running_memory_process(string|int $tracker, bool $time = true, bool $hash = true): bool
{
    global $memory_processes_table;
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $query = get_sql_query(
        $memory_processes_table,
        array("running", "next_repetition"),
        array(
            array("tracker", $hash ? string_to_integer($tracker, true) : $tracker)
        ),
        null,
        1
    );
    load_previous_sql_database();
    return !empty($query)
        && ($query[0]->running !== null
            || $time && $query[0]->next_repetition >= time());
}

function start_memory_process(string|int $tracker, bool $forceful = false, bool $hash = true): bool
{
    global $memory_processes_table;

    if ($hash) {
        $tracker = string_to_integer($tracker, true);
    }
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $query = get_sql_query(
        $memory_processes_table,
        array("running", "next_repetition"),
        array(
            array("tracker", $tracker)
        ),
        null,
        1
    );

    if (empty($query)) {
        if (sql_insert(
            $memory_processes_table,
            array(
                "tracker" => $tracker,
                "running" => 1,
                "next_repetition" => (time() + get_max_script_time())
            )
        )) {
            load_previous_sql_database();
            return true;
        }
    } else if (($query[0]->running === null || $query[0]->next_repetition < time())
        && set_sql_query(
            $memory_processes_table,
            array(
                "running" => 1,
                "next_repetition" => (time() + get_max_script_time())
            ),
            array(
                array("tracker", $tracker)
            )
        )) {
        load_previous_sql_database();
        return true;
    }
    if ($forceful) {
        return start_memory_process($tracker, $forceful, false);
    } else {
        load_previous_sql_database();
    }
    return false;
}

function end_memory_process(string|int $tracker, bool $hash = true): void
{
    global $memory_processes_table;
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    set_sql_query(
        $memory_processes_table,
        array(
            "running" => null
        ),
        array(
            array("tracker", $hash ? string_to_integer($tracker, true) : $tracker)
        ),
        null,
        1
    );
    load_previous_sql_database();
}
