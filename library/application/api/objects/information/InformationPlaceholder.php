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

    public function replace($string, $objects): string
    {
        foreach ($objects as $class => $object) {
            try {
                foreach ($object as $key => $value) {
                    $string = str_replace($this->build($class, $key), $value, $string);
                }
            } catch (Exception $ignored) {
            }
        }
        return $string;
    }

    public function build($class, $property): string
    {
        return self::STARTER . $class . self::DIVISOR . $property . self::ENDER;
    }
}