<?php

class __SchedulerDatabase
{

    private const TABLE_NAME = "scheduler.running";

    public static function isRunning(int $scriptHash): bool
    {
        return !empty(get_sql_query(
            self::TABLE_NAME,
            array(
                "id"
            ),
            array(
                array("script_hash" => $scriptHash),
                array("expiration_time", ">", time())
            ),
            null,
            1
        ));
    }

    public static function setRunning(int $scriptHash, int $serverHash): ?int
    {
        if (sql_insert(
            self::TABLE_NAME,
            array(
                "script_hash" => $scriptHash,
                "server_hash" => $serverHash,
                "expiration_time" => time() + 60
            )
        )) {
            return get_sql_connection()?->insert_id;
        } else {
            return null;
        }
    }

    public static function deleteSpecific(int $id): void
    {
        delete_sql_query(
            self::TABLE_NAME,
            array(
                array("id", $id)
            ),
            null,
            1
        );
    }

    public static function deleteOldRows(int $scriptHash): void
    {
        delete_sql_query(
            self::TABLE_NAME,
            array(
                array("script_hash" => $scriptHash),
                array("expiration_time", "<=", time())
            )
        );
    }

}
