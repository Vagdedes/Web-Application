<?php
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/sql.php';

unset($argv[0]);
$function = explode("/", array_shift($argv));
$function = array_pop($function);
$function = array("__SchedulerTasks", $function);
$refreshSeconds = round(array_shift($argv) * 1_000_000);
require_once '/var/www/.structure/library/scheduler/tasks.php';

while (true) {
    try {
        echo call_user_func_array($function, $argv) . "\n";
    } catch (Throwable $exception) {
        var_dump($exception->getMessage());
        $trace = $exception->getTraceAsString();
        $file = fopen(
            "/var/www/.structure/library/scheduler/errors/exception_" . string_to_integer($trace, true) . ".txt",
            "w"
        );
        echo $trace . "\n";

        if ($file !== false) {
            fwrite($file, $trace);
            fclose($file);
        }
    }
    if ($refreshSeconds > 0) {
        usleep($refreshSeconds);
    }
}