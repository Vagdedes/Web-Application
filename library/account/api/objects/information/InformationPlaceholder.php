<?php

class InformationPlaceholder
{

    public const
        STARTER = "%%__",
        DIVISOR = "_",
        ENDER = "__%%";

    public function __construct()
    {
    }

    public function replace(?string $string, array $objects): ?string
    {
        if ($string !== null) {
            foreach ($objects as $class => $object) {
                try {
                    foreach ($object as $key => $value) {
                        $string = str_replace($this->build($class, $key), $value, $string);
                    }
                } catch (Exception $ignored) {
                }
            }
        }
        return $string;
    }

    public function build(string $class, mixed $property): string
    {
        return self::STARTER . $class . self::DIVISOR . $property . self::ENDER;
    }
}