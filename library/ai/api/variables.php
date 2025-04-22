<?php

class AIField
{
    public const
        INTEGER = "INTEGER",
        INTEGER_ARRAY = "INTEGER ARRAY",
        STRING = "STRING",
        STRING_ARRAY = "STRING ARRAY",
        DECIMAL = "DOUBLE-PRECISION FLOATING-POINT",
        DECIMAL_ARRAY = "DOUBLE-PRECISION FLOATING-POINT ARRAY",
        BOOLEAN = "BOOLEAN",
        BOOLEAN_ARRAY = "BOOLEAN ARRAY",
        ABSTRACT_ARRAY = "ARRAY";
}

class AIModelFamily
{
    // Text to Text
    public const
        CHAT_GPT = 1,
        CHAT_GPT_PRO = 2,
        OPENAI_OMNI_MINI = 14;

    // Text to Image
    public const
        DALL_E_3 = 5,
        DALL_E_2 = 12;

    // Image And Text to Text
    public const
        OPENAI_VISION_PRO = 8,
        OPENAI_VISION = 9;

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