<?php

class AIHelper
{

    public static function wordToToken(int $count): int
    {
        $count *= AIProperties::WORD_TO_TOKEN;
        $count /= 100.0;
        $count = floor($count);
        return $count * 100;
    }

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
