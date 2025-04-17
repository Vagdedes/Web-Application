<?php

function local_sql_delete_outdated_cache(): string
{
    return strval(sql_delete_outdated_cache());
}