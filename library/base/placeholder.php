<?php

class InformationPlaceholder
{

    public const
        STARTER = "%%__",
        DIVISOR = " ",
        DIVISOR_REPLACEMENT = "_",
        ENDER = "__%%";

    private string $starter, $divisor, $divisorReplacement, $ender;
    private array $replacements;

    public function __construct(string $starter = null,
                                string $divisor = null,
                                string $divisorReplacement = null,
                                string $ender = null)
    {
        $this->starter = $starter !== null ? $starter : self::STARTER;
        $this->divisor = $divisor !== null ? $divisor : self::DIVISOR;
        $this->divisorReplacement = $divisorReplacement !== null ? $divisorReplacement : self::DIVISOR_REPLACEMENT;
        $this->ender = $ender !== null ? $ender : self::ENDER;
        $this->replacements = array();
    }

    public function replace(string|array|object|null $input): string|array|object|null
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->build($value, $this->replacements);
            }
            return $input;
        } else if (is_object($input)) {
            $object = new stdClass();

            foreach (json_decode(json_encode($input), true) as $key => $value) {
                $object->{$key} = $this->build($value, $this->replacements);
            }
            return $object;
        } else {
            return $this->build($input, $this->replacements);
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->replacements[$key] = $value;
    }

    public function setAll(array|object $array): void
    {
        if (is_object($array)) {
            $array = json_decode(json_encode($array), true);
        }
        foreach ($array as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function add(string $key, mixed $value): void
    {
        if (!array_key_exists($key, $this->replacements)) {
            $this->replacements[$key] = $value;
        }
    }

    public function addAll(array|object $array): void
    {
        if (is_object($array)) {
            $array = json_decode(json_encode($array), true);
        }
        foreach ($array as $key => $value) {
            $this->add($key, $value);
        }
        $this->addDefaults();
    }

    public function getReplacements(): array
    {
        return $this->replacements;
    }

    private function addDefaults(): void
    {
        $this->add("year", date("Y"));
        $this->add("month", date("m"));
        $this->add("day", date("d"));
        $this->add("hour", date("H"));
        $this->add("minute", date("i"));
        $this->add("second", date("s"));
    }

    private function build(?string $input, array|object $replacements): ?string
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
