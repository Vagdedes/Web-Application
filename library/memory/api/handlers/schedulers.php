<?php

function schedule_function_in_memory($function, $arguments = null, int $seconds = 1, ?bool $processEnd = null): void
{
    global $memory_schedulers_table;
    $identifier = string_to_integer($function, true);
    load_sql_database(SqlDatabaseCredentials::MEMORY);
    $query = get_sql_query(
        $memory_schedulers_table,
        array("next_repetition"),
        array(
            array("tracker", $identifier),
        ),
        null,
        1
    );
    $isProcess = is_bool($processEnd);

    if (empty($query)) {
        if (sql_insert(
                $memory_schedulers_table,
                array(
                    "tracker" => $identifier,
                    "next_repetition" => time() + $seconds
                )
            )
            && (!$isProcess || start_memory_process($identifier, false, false))) {
            load_previous_sql_database();
            call_user_func_array($function, $arguments === null ? array() : $arguments);

            if ($isProcess && $processEnd) {
                end_memory_process($identifier, false);
            }
        }
    } else if ($query[0]->next_repetition <= time()
        && set_sql_query(
            $memory_schedulers_table,
            array(
                "next_repetition" => time() + $seconds
            ),
            array(
                array("tracker", $identifier)
            )
        )
        && (!$isProcess || start_memory_process($identifier, false, false))) {
        load_previous_sql_database();
        call_user_func_array($function, $arguments === null ? array() : $arguments);

        if ($isProcess && $processEnd) {
            end_memory_process($identifier, false);
        }
    } else {
        load_previous_sql_database();
    }
}
