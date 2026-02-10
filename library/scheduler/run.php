<?php

use React\EventLoop\Loop;

ini_set('memory_limit', '-1');
ini_set('error_log', '/var/log/apache2/error.log');

require '/var/www/.structure/library/base/vendor.php';
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
$callable = function () use ($function, $argv) {
    echo call_user_func_array($function, $argv) . "\n";
};

__SchedulerDatabase::deleteOldRows($scriptHash);

$start = time();
$loop = Loop::get();
$loop->addPeriodicTimer($refreshSeconds, function ()
use ($start, $function, $argv, $uniqueRun, $scriptHash, $serverHash, &$runningId, $callable) {
    try {
        if (time() - $start > 60) {
            exit();
        } else if ($uniqueRun) {
            if (!__SchedulerDatabase::isRunning($scriptHash)) {
                $runningId = __SchedulerDatabase::setRunning($scriptHash, $serverHash);
                $callable();

                if ($runningId !== null) {
                    __SchedulerDatabase::deleteSpecific($runningId);
                }
            }
        } else {
            $callable();
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        error_log($exception->getTraceAsString());

        if ($runningId !== null) {
            __SchedulerDatabase::deleteSpecific($runningId);
        }
        exit();
    }
});
$loop->run();