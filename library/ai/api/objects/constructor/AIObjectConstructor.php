<?php

class AIObjectConstructor
{

    public const DEFAULT_INSTRUCTIONS =
        [
            "Find the object and return it in JSON format without markdown",
            "If one non-nullable parameter is not found, fail the rest of the parameters"
        ];

    private array $initiators, $tasks, $parents;

    public function __construct(
        array $initiators,
        array $tasks = [],
        bool  $addDefaultInstructions = true
    )
    {
        $this->initiators = $initiators;

        if (empty($tasks)) {
            $tasks = self::DEFAULT_INSTRUCTIONS;
        } else if ($addDefaultInstructions) {
            foreach (self::DEFAULT_INSTRUCTIONS as $instruction) {
                if (!in_array($instruction, $tasks)) {
                    array_unshift($tasks, $instruction);
                }
            }
        }
        $this->tasks = $tasks;
        $this->parents = array();
    }

    public function build(): ?string
    {
        $object = new stdClass();
        $object->tasks = $this->tasks;
        $object->object = $this->prepare($this->initiators);
        return @json_encode($object) ?? null;
    }

    public function get(mixed $information, bool $strict = true): ?object
    {
        $array = array();
        $this->findInitiators($array, $this->initiators);

        if (is_string($information)) {
            $object = @json_decode($information, false);
        } else if (is_array($information)) {
            $object = json_decode(json_encode($information), false);
        } else if (is_object($information)) {
            $object = $information;
        } else {
            return null;
        }

        if (is_object($object)) {
            foreach ($array as $initiator) {
                if ($initiator instanceof AIFieldObject) {
                    $parents = $initiator->getParents();
                    $parent = array_shift($parents);
                    $oldObject = null;

                    if (isset($object->{$parent})) {
                        $oldObject = $object;
                        $value = $object->{$parent};

                        if (!empty($parents)) {
                            foreach ($parents as $parent) {
                                $oldObject = $value;
                                $value = $value->{$parent} ?? null;
                            }
                        }
                    } else {
                        $oldObject = $object;
                        $value = null;
                    }

                    if ($value === null
                        || is_string($value) && strlen($value) === 0) {
                        if ($initiator->isNullable()) {
                            $oldObject->{$parent} = null;
                            continue;
                        } else {
                            return null;
                        }
                    }

                    switch ($initiator->getType()) {
                        case AIField::INTEGER;
                        case AIField::DECIMAL;
                            if (!is_numeric($value)
                                && !is_float($value)
                                && !is_int($value)) {
                                if ($strict || !$initiator->isNullable()) {
                                    return null;
                                } else {
                                    $oldObject->{$parent} = null;
                                }
                            } else {
                                if ($strict) {
                                    if (strlen($value) > $initiator->getMaxLength()) {
                                        return null;
                                    }
                                } else if (strlen($value) > $initiator->getMaxLength()) {
                                    $oldObject->{$parent} = substr($value, 0, $initiator->getMaxLength());
                                }
                            }
                            break;
                        case AIField::STRING;
                            if (!is_string($value)) {
                                if ($strict || !$initiator->isNullable()) {
                                    return null;
                                } else {
                                    $oldObject->{$parent} = null;
                                }
                            } else {
                                if ($strict) {
                                    if (strlen($value) > $initiator->getMaxLength()) {
                                        return null;
                                    }
                                } else if (strlen($value) > $initiator->getMaxLength()) {
                                    $oldObject->{$parent} = substr($value, 0, $initiator->getMaxLength());
                                }
                            }
                            break;
                        case AIField::BOOLEAN;
                            $value = is_string($value)
                                ? trim(strtolower($value))
                                : $value;

                            if ($strict
                                && ($value !== "true"
                                    && $value !== "false")) {
                                return null;
                            }
                            $oldObject->{$parent} = $value === true || $value === "true";
                            break;
                        case AIField::INTEGER_ARRAY;
                        case AIField::DECIMAL_ARRAY;
                        case AIField::STRING_ARRAY;
                        case AIField::BOOLEAN_ARRAY;
                        case AIField::ABSTRACT_ARRAY;
                            if (!is_array($value)) {
                                if ($strict || !$initiator->isNullable()) {
                                    return null;
                                } else {
                                    $oldObject->{$parent} = array();
                                    $value = array();
                                }
                            }
                            if ($strict) {
                                if (sizeof($value) > $initiator->getMaxLength()) {
                                    return null;
                                }
                            } else if (sizeof($value) > $initiator->getMaxLength()) {
                                $oldObject->{$parent} = array_slice($value, 0, $initiator->getMaxLength());
                            }
                            if (!empty($value)) {
                                switch ($initiator->getType()) {
                                    case AIField::DECIMAL_ARRAY:
                                    case AIField::INTEGER_ARRAY:
                                        foreach ($value as $key => $val) {
                                            if (!is_numeric($val)
                                                && !is_float($val)
                                                && !is_int($val)) {
                                                if ($strict) {
                                                    return null;
                                                } else {
                                                    unset($oldObject->{$parent}[$key]);
                                                }
                                            } else {
                                                if ($strict) {
                                                    if (strlen($val) > $initiator->getMaxLength()) {
                                                        return null;
                                                    }
                                                } else if (strlen($val) > $initiator->getMaxLength()) {
                                                    $oldObject->{$parent}[$key] = substr($val, 0, $initiator->getMaxLength());
                                                }
                                            }
                                        }
                                        break;
                                    case AIField::BOOLEAN_ARRAY:
                                        foreach ($value as $key => $val) {
                                            $val = is_string($val)
                                                ? trim(strtolower($val))
                                                : $val;

                                            if ($strict
                                                && ($val !== "true"
                                                    && $val !== "false")) {
                                                return null;
                                            }
                                            $oldObject->{$parent}[$key] = $val === true || $val === "true";
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                            break;
                        default:
                            return null;
                    }
                }
            }
            return $object;
        }
        return null;
    }

    private function findInitiators(array &$array, array $initiators): void
    {
        foreach ($initiators as $initiator) {
            if ($initiator instanceof AIFieldObject) {
                $array[] = $initiator;
            } else if (is_array($initiator)) {
                $this->findInitiators($array, $initiator);
            }
        }
    }

    private function prepare(array &$initiators, ?string $originalKey = null, bool $first = true): object
    {
        $object = new stdClass();

        if (!empty($initiators)) {
            foreach ($initiators as $key => &$initiator) {
                if ($initiator instanceof AIFieldObject) {
                    if ($originalKey !== null
                        && array_key_exists($originalKey, $this->parents)) {
                        $initiator->addParents($this->parents[$originalKey]);
                    }
                    $initiator->addParent($key);
                    $subObject = new stdClass();
                    $subObject->type = $initiator->getType();
                    $subObject->max_length = $initiator->getMaxLength();
                    $subObject->is_nullable = $initiator->isNullable();
                    $subObject->definition = $initiator->getDefinition();

                    if (!empty($initiator->getEnums())) {
                        $subObject->enums = $initiator->getEnums();
                    }
                    if (!empty($initiator->getDefault())) {
                        $subObject->default = $initiator->getDefault();
                    }
                    $object->{$key} = $subObject;
                } else if (is_array($initiator)) {
                    $arrayKey = $originalKey ?? $key;

                    if (array_key_exists($arrayKey, $this->parents)) {
                        $this->parents[$arrayKey][] = $key;
                    } else {
                        $this->parents[$arrayKey] = array($key);
                    }
                    $object->{$key} = $this->prepare($initiator, $arrayKey, false);
                }
            }
        }

        if ($first) {
            $this->parents = array();
        }
        return $object;
    }

}
