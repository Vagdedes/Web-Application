<?php

class MinecraftPlatformConverter
{

    private ?int $conversion;

    function __construct(string $platform)
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

    public function getConversion(): ?int
    {
        return $this->conversion;
    }
}
