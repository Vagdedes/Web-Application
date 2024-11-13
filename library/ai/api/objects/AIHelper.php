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

}
