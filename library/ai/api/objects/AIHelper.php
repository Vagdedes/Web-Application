<?php

class AIHelper
{

    public static function isLinearImageModel(int|AIModel $modelFamily): bool
    {
        if ($modelFamily instanceof AIModel) {
            $modelFamily = $modelFamily->getFamilyID();
        }
        switch ($modelFamily) {
            case AIModelFamily::GPT_IMAGE:
                return true;
            default:
                return false;
        }
    }

    public static function isReasoningModel(int|AIModel $modelFamily): bool
    {
        if ($modelFamily instanceof AIModel) {
            $modelFamily = $modelFamily->getFamilyID();
        }
        switch ($modelFamily) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::CHAT_GPT_NANO:
                return true;
            default:
                return false;
        }
    }

    public static function supportsVerbosity(int|AIModel $modelFamily): bool
    {
        if ($modelFamily instanceof AIModel) {
            $modelFamily = $modelFamily->getFamilyID();
        }
        switch ($modelFamily) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::CHAT_GPT_NANO:
                return true;
            default:
                return false;
        }
    }

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

}
