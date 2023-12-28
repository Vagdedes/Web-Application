<?php

class InformationPlaceholder
{

    public const
        STARTER = "%%__",
        DIVISOR = " ",
        DIVISOR_REPLACEMENT = "_",
        ENDER = "__%%";

    private string $starter, $divisor, $divisorReplacement, $ender;

    public function __construct(string $starter = null,
                                string $divisor = null,
                                string $divisorReplacement = null,
                                string $ender = null)
    {
        $this->starter = $starter !== null ? $starter : self::STARTER;
        $this->divisor = $divisor !== null ? $divisor : self::DIVISOR;
        $this->divisorReplacement = $divisorReplacement !== null ? $divisorReplacement : self::DIVISOR_REPLACEMENT;
        $this->ender = $ender !== null ? $ender : self::ENDER;
    }

    public function replace(string|array|object|null $input, array|object|null $replacements): string|array|object|null
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->build($value, $replacements);
            }
            return $input;
        } else if (is_object($input)) {
            $object = new stdClass();

            foreach (json_decode(json_encode($input), true) as $key => $value) {
                $object->{$key} = $this->build($value, $replacements);
            }
            return $object;
        } else {
            return $this->build($input, $replacements);
        }
    }

    private function build(?string $input, array|object|null $replacements): ?string
    {
        if ($input === null || empty($replacements)) {
            return $input;
        } else {
            if (is_object($replacements)) {
                $replacements = json_decode(json_encode($replacements), true);
            }
            foreach ($replacements as $current => $replacement) {
                $input = str_replace(
                    $this->starter . str_replace($this->divisor, $this->divisorReplacement, $current) . $this->ender,
                    $replacement,
                    $input
                );
            }
            return $input;
        }
    }
}
