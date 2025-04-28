<?php

use React\EventLoop\Loop;

ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';
require '/var/www/.structure/library/base/utilities.php';
require '/var/www/.structure/library/base/sql.php';
require '/var/www/.structure/library/base/communication.php';
require '/var/www/.structure/library/scheduler/tasks.php';
require '/var/www/.structure/library/scheduler/database.php';

unset($argv[0]);
$function = explode("/", array_shift($argv));
$function = array_pop($function);
$function = array("__SchedulerTasks", $function);
$refreshSeconds = array_shift($argv);
$uniqueRun = array_shift($argv) == "true";
$scriptHash = array_to_integer(
    array(
        __FILE__,
        $function[0],
        $function[1],
    ),
    true
);
$serverHash = get_server_identifier(true);
$runningId = null;

__SchedulerDatabase::deleteOldRows($scriptHash);

$start = time();
$loop = Loop::get();
$loop->addPeriodicTimer($refreshSeconds, function ()
use ($start, $function, $argv, $uniqueRun, $scriptHash, $serverHash, &$runningId) {
    try {
        if (time() - $start > 60
            || has_sql_connections()
            && !is_sql_usable()) {
            exit();
        } else {
            if (!$uniqueRun
                || !__SchedulerDatabase::isRunning($scriptHash)) {
                $callable = function () use ($function, $argv) {
                    echo call_user_func_array($function, $argv) . "\n";
                };

                if ($uniqueRun) {
                    $runningId = __SchedulerDatabase::setRunning($scriptHash, $serverHash);
                    $callable();

                    if ($runningId !== null) {
                        __SchedulerDatabase::deleteSpecific($runningId);
                    }
                } else {
                    $callable();
                }
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
        if ($runningId !== null) {
            __SchedulerDatabase::deleteSpecific($runningId);
        }
        exit();
    }
});
$loop->run();