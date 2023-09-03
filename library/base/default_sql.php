<?php
require_once '/var/www/.structure/library/base/sql.php';

if (!has_sql_credentials()) {
    $sql_credentials = get_keys_from_file("/var/www/.structure/private/sql_credentials", 3);

    if ($sql_credentials === null) {
        echo "database failure";
        return;
    }
    sql_sql_credentials($sql_credentials[0],
        $sql_credentials[1],
        $sql_credentials[2],
        null,
        null,
        null,
        true);
}
