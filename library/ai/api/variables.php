<?php

class AIField
{
    public const
        INTEGER = "INTEGER",
        INTEGER_ARRAY = "INTEGER_ARRAY",
        STRING = "STRING",
        STRING_ARRAY = "STRING_ARRAY",
        DECIMAL = "FLOAT",
        DECIMAL_ARRAY = "FLOAT_ARRAY",
        BOOLEAN = "BOOLEAN",
        BOOLEAN_ARRAY = "BOOLEAN_ARRAY",
        ABSTRACT_ARRAY = "GENERIC_ARRAY";
}

class AIModelFamily
{
    // Text to Text
    public const
        CHAT_GPT = 1,
        CHAT_GPT_PRO = 2,
        CHAT_GPT_NANO = 16,
        CHAT_GPT_NANO_OLD = 19;

    // Text to Image
    public const
        GPT_IMAGE_1 = 15,
        DALL_E_3 = 5,
        DALL_E_2 = 12;

    // Image And Text to Text
    public const
        OPENAI_VISION_PRO = self::CHAT_GPT_PRO,
        OPENAI_VISION_NANO = self::CHAT_GPT_NANO,
        OPENAI_VISION = self::CHAT_GPT;

    // Audio And Text to Text Or Audio
    public const
        OPENAI_SOUND_PRO = 10,
        OPENAI_SOUND = 13;

    // Speech to Text
    public const
        OPENAI_SPEECH = 3,
        OPENAI_SPEECH_PRO = 4,
        OPENAI_SPEECH_OLD = 6;

    // Text to Speech
    public const
        OPENAI_SPEECH_TEXT = 7;

    // Text to Embedding
    public const
        OPENAI_EMBEDDING_SMALL = 17,
        OPENAI_EMBEDDING_LARGE = 18;
}

class AIAuthorization
{
    public const
        OPENAI = "openai_credentials";
}

class AIDatabaseTable
{
    public const
        AI_MODELS = "artificial_intelligence.models",
        AI_HISTORY = "artificial_intelligence.history",
        AI_CURRENCIES = "artificial_intelligence.currencies",
        AI_PRICING = "artificial_intelligence.pricing";
}