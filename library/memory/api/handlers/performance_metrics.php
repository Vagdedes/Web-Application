<?php
$memory_metrics_performance_time = 0;

function performance_metrics_reset(): void
{
    global $memory_metrics_performance_time;
    $memory_metrics_performance_time = microtime(true);
}

function performance_metrics_store(mixed $name): void
{
    global $memory_metrics_performance_time;
    $memory_metrics_performance_time = microtime(true) - $memory_metrics_performance_time;

    if ($memory_metrics_performance_time > 0.0) {
        global $memory_performance_metrics_table;
        load_sql_database(SqlDatabaseCredentials::MEMORY);
        sql_insert(
            $memory_performance_metrics_table,
            array(
                "identifier" => $name,
                "duration" => $memory_metrics_performance_time,
                "creation_date" => get_current_date()
            )
        );
        load_previous_sql_database();
    }
}