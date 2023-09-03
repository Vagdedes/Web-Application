<?php

class MinecraftPlatformConverter
{
    public const
        SPIGOTMC_PLATFORM = 1,
        BUILTBYBIT_PLATFORM = 2,
        POLYMART_PLATFORM = 3;
    private ?int $conversion;

    function __construct($platform)
    {
        $this->conversion = null;

        if (is_numeric($platform)) {
            $query = get_accepted_platforms(array("id"), $platform);

            if (!empty($query)) {
                $this->conversion = $platform;
            }
        } else if (!empty($platform)) {
            $query = get_accepted_platforms(array("id", "name_aliases"));

            if (!empty($query)) {
                foreach ($query as $row) {
                    if (in_array($platform, explode("|", $row->name_aliases))) {
                        $this->conversion = $row->id;
                        break;
                    }
                }
            }
        }
    }

    public function getConversion()
    {
        return $this->conversion;
    }
}
