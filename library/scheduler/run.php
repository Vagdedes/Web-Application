<?php
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';
require '/var/www/.structure/library/base/utilities.php';
require '/var/www/.structure/library/base/sql.php';
require '/var/www/.structure/library/base/communication.php';
require '/var/www/.structure/library/scheduler/tasks.php';

unset($argv[0]);
$function = explode("/", array_shift($argv));
$function = array_pop($function);
$function = array("__SchedulerTasks", $function);
$refreshSeconds = round(array_shift($argv) * 1_000_000);

while (true) {
    try {
        if (has_sql_connections()
            && !is_sql_usable()) {
            exit();
            break;
        } else {
            echo call_user_func_array($function, $argv) . "\n";

            if ($refreshSeconds > 0) {
                usleep($refreshSeconds);
            }
        }
    } catch (Throwable $exception) {
        $object = new stdClass();
        $object->date = get_current_date();
        $object->message = $exception->getMessage();
        $object->trace = $exception->getTraceAsString();
        $trace = json_encode($object, JSON_PRETTY_PRINT);
        $file = fopen(
            "/var/www/.structure/library/scheduler/errors/exception_" . string_to_integer($trace, true) . ".txt",
            "w"
        );
        echo $trace . "\n";

        if ($file !== false) {
            fwrite($file, $trace);
            fclose($file);
        }
        exit();
        break;
    }
}