<?php

class AIObjectConstructor
{

    public static function build(
        array            $initiators,
        int|string|float $returnOnFail = 0,
        array            $tasks = []
    ): ?string
    {
        $object = new stdClass();

        if (empty($tasks)) {
            $tasks[] = "Find the object and return it without markdown";
        }
        $object->tasks = $tasks;
        $object->object = self::prepare($initiators);
        $object->return_if_criteria_not_met = $returnOnFail;
        return @json_encode($object) ?? null;
    }

    private static function prepare(array $initiators): object
    {
        $object = new stdClass();

        foreach ($initiators as $key => $initiator) {
            if ($initiator instanceof AIFieldObjectInitiator) {
                $subObject = new stdClass();
                $subObject->type = $initiator->getType()->getType();
                $subObject->max_length = $initiator->getType()->getLength();
                $subObject->is_nullable = $initiator->getType()->isNullable();
                $subObject->fail_if_criteria_not_met = $initiator->getType()->canFail();
                $subObject->definition = $initiator->getDefinition();
                $object->{$initiator->getName()} = $subObject;
            } else if (is_array($initiator)) {
                $object->{$key} = self::prepare($initiator);
            }
        }
        return $object;
    }

}
