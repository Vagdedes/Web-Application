<?php
$localVariablesTable = "memory.localVariables";
$max32bitInteger = 2147483647;
$oldRowsToDeleteWhenFull = 50;

$keyStorageTable = "memory.keyStorage";
$rowKeyMaxLength = 64;

$keyValuePairsTable = "memory.keyValuePairs";
$valuePairMaxLength = 16314;

$keyCooldownsTable = "memory.keyCooldowns";
$keyLimitsTable = "memory.keyLimits";

$cooldownPerMemoryTable = array(
    $keyCooldownsTable => "30 minutes",
    $keyLimitsTable => "15 minutes",
    $keyValuePairsTable => "10 minutes",
    $localVariablesTable => null
);

$requiredRowsToTruncate = array(
    $keyCooldownsTable => 100,
    $keyLimitsTable => 100,
    $keyValuePairsTable => 500,
);