<?php
$memory_reserved_keys = array(
    0 => 0xff1, // Reserved for caching memory segment ids
    1 => 0xff2 // Reserved for caching memory segment id difference
);

$memory_permissions = 0644;
$memory_starting_bytes = 2;

$memory_reserved_names = array("cooldowns", "limits", "keyValuePairs");

$memory_clearance_table = "memory.clearMemory";
$memory_clearance_tracking_table = "memory.clearMemoryTracking";
$memory_schedulers_table = "memory.schedulers";
$memory_performance_metrics_table = "memory.performanceMetrics";
$memory_processes_table = "memory.processes";

$memory_clearance_past = 60; // 1 minute
$memory_clearance_row_limit = 50;


