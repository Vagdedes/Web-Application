<?php
$session = getAccountSession1();

if (is_private_connection()) {
    function getCPUCores()
    {
        return (int)shell_exec("cat /proc/cpuinfo | grep processor | wc -l");
    }

    function getFreeRam()
    {
        $exec_free = explode("\n", trim(shell_exec('free')));
        $get_mem = preg_split("/[\s]+/", $exec_free[1]);
        $mem = (int)($get_mem[2] / 1024);
        return getMaxRam() - $mem;
    }

    function getMaxRam()
    {
        $exec_free = explode("\n", trim(shell_exec('free')));
        $get_mem = preg_split("/[\s]+/", $exec_free[1]);
        $mem = (int)($get_mem[1] / 1024);
        return $mem;
    }

    function askapache_get_process_count()
    {
        static $ver, $runs = 0;

        if (is_null($ver)) {
            $ver = version_compare(PHP_VERSION, '5.3.0', '>=');

            if ($runs++ > 0) {
                if ($ver) {
                    clearstatcache(true, '/proc');
                } else {
                    clearstatcache();
                }
            }
        }
        $stat = stat('/proc');
        return ((false !== $stat && isset($stat[3])) ? $stat[3] : 0);
    }

    $object = new stdClass();
    $object->cpu_cores = getCPUCores();
    $object->cpu_processes = askapache_get_process_count();
    $object->free_ram = getFreeRam();
    $object->max_ram = getMaxRam();
    $object->free_disk = (int)(disk_free_space("/") / 1024 / 1024);
    $object->max_disk = (int)(disk_total_space("/") / 1024 / 1024);
    echo json_encode($object);
}
