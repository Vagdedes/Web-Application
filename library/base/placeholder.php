<?php

class InformationPlaceholder
{

    public const
        STARTER = "%%__",
        DIVISOR = " ",
        DIVISOR_REPLACEMENT = "__",
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
                $input[$key] = $this->build($value);
            }
            return $input;
        } else if (is_object($input)) {
            $object = new stdClass();

            foreach (@json_decode(@json_encode($input), true) as $key => $value) {
                $object->{$key} = $this->build($value);
            }
            return $object;
        } else {
            return $this->build($input);
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->replacements[$key] = $value;
    }

    public function setAll(array|object $array): void
    {
        if (is_object($array)) {
            $array = @json_decode(@json_encode($array), true);
        }
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $this->set($key, $value);
            }
        }
    }

    public function add(string $key, mixed $value, bool $defaults = true): void
    {
        $this->addLocal($key, $value);

        if ($defaults) {
            $this->addDefaults();
        }
    }

    private function addLocal(string $key, mixed $value): void
    {
        if (is_string($value)
            && !array_key_exists($key, $this->replacements)) {
            $this->replacements[$key] = $value;
        }
    }

    public function addAll(array|object $array, bool $defaults = true): void
    {
        if (is_object($array)) {
            $array = @json_decode(@json_encode($array), true);
        }
        foreach ($array as $key => $value) {
            $this->add($key, $value);
        }
        if ($defaults) {
            $this->addDefaults();
        }
    }

    public function getReplacements(): array
    {
        return $this->replacements;
    }

    private function addDefaults(): void
    {
        $this->addLocal("year", date("Y"));
        $this->addLocal("month", date("m"));
        $this->addLocal("day", date("d"));
        $this->addLocal("hour", date("H"));
        $this->addLocal("minute", date("i"));
        $this->addLocal("second", date("s"));
    }

    private function build(mixed $input): mixed
    {
        if ($input !== null
            && !is_array($input)
            && !is_object($input)
            && !empty($this->replacements)) {
            foreach ($this->replacements as $current => $replacement) {
                $input = str_replace(
                    $this->starter . str_replace($this->divisor, $this->divisorReplacement, $current) . $this->ender,
                    $replacement,
                    $input
                );
            }
        }
        return $input;
    }
}
