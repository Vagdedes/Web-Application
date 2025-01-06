<?php

class AIHelper
{

    public static function getTokens(string $reference, string|array $context, bool $model = false): int
    {
        if (is_array($context)) {
            $count = 0;

            foreach ($context as $value) {
                $count += self::getTokens(
                    $reference,
                    $value,
                    $model
                );
            }
            return $count;
        } else if ($model) {
            return sizeof(
                TokenizerEncodingFactory::createByModelName(
                    $reference
                )->encode(
                    $context
                )
            );
        } else {
            return sizeof(
                TokenizerEncodingFactory::createByEncodingName(
                    $reference
                )->encode(
                    $context
                )
            );
        }
    }

    // Separator

    public static function getCurrency(int $id): ?object
    {
        $query = get_sql_query(
            AIDatabaseTable::AI_CURRENCIES,
            null,
            array(
                array("id", $id),
                array("deletion_date", null),
            ),
            null,
            1
        );
        return empty($query) ? null : $query[0];
    }

    // Separator

    public static function getAuthorization(string $key): ?string
    {
        switch ($key) {
            case AIAuthorization::OPENAI:
                return get_keys_from_file($key, 1)[0] ?? null;
            default:
                return null;
        }
    }

    // Separator

    private static function requestAIFieldString(string $type): string
    {
        return "YOU MUST ONLY AND STRICTLY RETURN AN ANSWER CONSISTING OF A '" . $type . "' BASED ON THE USER'S CONTENT.";
    }

    public static function requestAIField(array $fieldType): string
    {
        return self::requestAIFieldString($fieldType["name"]);
    }

    public static function findAndRequestAIField(array $fieldTypes): string
    {
        $size = sizeof($fieldTypes);

        if ($size <= 1) {
            return self::requestAIField(
                $fieldTypes[0] ?? null,
            );
        } else {
            $size--;
            $types = "";

            foreach ($fieldTypes as $count => $fieldType) {
                $types .= "'" . $fieldType["name"] . "'";

                if ($count != $size) {
                    $types .= " or ";
                }
            }
            return self::requestAIFieldString($types);
        }
    }

    public static function getAIField(array $fieldType, string $reply): string|int|null|float|bool|array|object
    {
        switch ($fieldType["type"]) {
            case AIField::INTEGER["type"]:
                if (is_numeric($reply)) {
                    $int = (int)$reply;
                    return $reply == $int ? $int : null;
                }
                break;
            case AIField::DECIMAL["type"]:
                var_dump($reply);
                if (is_numeric($reply)) {
                    $flt = (float)$reply;
                    return $reply == $flt ? $flt : null;
                }
                break;
            case AIField::STRING["type"]:
                return $reply;
            case AIField::BOOLEAN["type"]:
                $reply = strtolower($reply);

                if ($reply === "true") {
                    return true;
                } else if ($reply === "false") {
                    return false;
                }
                break;
            case AIField::ARRAY:
                $array = @json_decode($reply, true);
                return is_array($array) ? $array : null;
            case AIField::OBJECT:
                $object = @json_decode($reply);
                return is_object($object) ? $object : null;
            default:
                break;
        }
        return null;
    }

}
