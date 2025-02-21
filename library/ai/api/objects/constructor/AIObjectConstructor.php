<?php

class AIObjectConstructor
{

    private array $initiators, $tasks, $parents;

    public function __construct(
        array $initiators,
        array $tasks = []
    )
    {
        $this->initiators = $initiators;

        if (empty($tasks)) {
            $tasks[] = "Find the object and return it without markdown";
        }
        $this->tasks = $tasks;
        $this->parents = array();
    }

    public function build(): ?string
    {
        $object = new stdClass();
        $object->tasks = $this->tasks;
        $object->object = $this->prepare($this->initiators);
        $object->return_if_criteria_not_met = "{}";
        return @json_encode($object) ?? null;
    }

    public function get(string $information, ?array $initiators = null): ?object
    {
        if ($initiators === null) {
            $initiators = $this->initiators;
        }
        $object = @json_decode($information, false);

        if (is_object($object)) {
            foreach ($initiators as $key => $initiator) {
                if ($initiator instanceof AIFieldObject) {
                    if (sizeof($initiator->getParents()) > 0) {
                        $value = null;

                        foreach ($initiator->getParents() as $parent) {
                            if ($value === null) {
                                $value = $object->{$parent} ?? null;
                            } else {
                                $value = $value->{$parent} ?? null;

                                if ($value === null) {
                                    return null;
                                }
                            }
                        }
                    } else {
                        $value = $object->{$key} ?? null;
                    }

                    if ($object === null) {
                        if (!$initiator->isNullable()) {
                            return null;
                        } else {
                            $value->{$key} = null;
                        }
                    }
                    switch ($initiator->getType()) {
                        case AIField::INTEGER;
                        case AIField::DECIMAL;
                            if (!is_numeric($value)
                                || strlen($value) > $initiator->getMaxLength()) {
                                return null;
                            }
                            break;
                        case AIField::STRING;
                            if (!is_string($value)
                                || strlen($value) > $initiator->getMaxLength()) {
                                return null;
                            }
                            break;
                        case AIField::BOOLEAN;
                            if (!is_bool($value)
                                || strlen($value) > $initiator->getMaxLength()) {
                                return null;
                            }
                            break;
                        case AIField::INTEGER_ARRAY;
                        case AIField::DECIMAL_ARRAY;
                        case AIField::STRING_ARRAY;
                        case AIField::BOOLEAN_ARRAY;
                        case AIField::ABSTRACT_ARRAY;
                            if (!is_array($value)
                                || sizeof($value) > $initiator->getMaxLength()) {
                                return null;
                            }
                            break;
                        default:
                            return null;
                    }
                } else if (is_array($initiator)) {
                    $object = $this->get($information, $initiator);

                    if ($object === null) {
                        return null;
                    }
                }
            }
            return $object;
        }
        return null;
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
                    $subObject->fail_if_criteria_not_met = $initiator->canFail();
                    $subObject->definition = $initiator->getDefinition();
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
