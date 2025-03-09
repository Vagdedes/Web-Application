<?php

class HetznerComparison
{

    public static function getServerLevel(HetznerServer $server, bool $storage = false): int
    {
        if ($server->type instanceof HetznerX86Server) {
            global $HETZNER_X86_SERVERS;

            foreach ($HETZNER_X86_SERVERS as $key => $value) {
                if ($server->type->getCpuCores() === $value->getCpuCores()
                    && $server->type->getMemoryGB() === $value->getMemoryGB()
                    && $server->type->getName() === $value->getName()
                    && (!$storage || $server->type->getStorageGB() === $value->getStorageGB())) {
                    return $key;
                }
            }
        } else {
            global $HETZNER_ARM_SERVERS;

            foreach ($HETZNER_ARM_SERVERS as $key => $value) {
                if ($server->type->getCpuCores() === $value->getCpuCores()
                    && $server->type->getMemoryGB() === $value->getMemoryGB()
                    && $server->type->getName() === $value->getName()
                    && (!$storage || $server->type->getStorageGB() === $value->getStorageGB())) {
                    return $key;
                }
            }
        }
        return -1;
    }

    public static function shouldConsiderServer(HetznerServer|string $server): bool
    {
        return starts_with(
            is_string($server) ? $server : $server->name,
            HetznerVariables::HETZNER_SERVER_NAME_PATTERN
        );
    }

    public static function findLeastLevelServer(array $servers, bool $delete = false): ?HetznerServer
    {
        $min = null;

        foreach ($servers as $server) {
            if (!($server instanceof HetznerServer)) {
                continue;
            }
            if ((!$delete || $server->canDelete())
                && ($min === null || self::getServerLevel($server) < self::getServerLevel($min))) {
                $min = $server;
            }
        }
        return $min;
    }

}
