<?php

class AIParameterType
{
    public const
        JSON = 1;
}

class AIModelType
{
    public const
        TEXT_TO_TEXT = 1,

        TEXT_TO_IMAGE = 2,
        IMAGE_AND_TEXT_TO_TEXT = 5,

        TEXT_TO_SPEECH = 3,
        SPEECH_TO_TEXT = 4,

        SOUND_GENERATION = 6;
}

class AIModelFamily
{
    // Text to Text
    public const
        CHAT_GPT = 1,
        CHAT_GPT_PRO = 2,
        OPENAI_O1 = 3,
        OPENAI_O1_MINI = 4;

    // Text to Image
    public const
        DALLE_3 = 5;

    // Text to Speech
    public const
        TEXT_TO_SPEECH = 6;

    // Speech to Text
    public const
        WHISPER = 7;

    // Image to Text
    public const
        VISION_PRO = 8,
        VISION = 9;

    // Sound Generation
    public const
        SOUND_GENERATION = 10;
}

class AICurrency
{
    public const USD = 1;
}

class AIDatabaseTable
{
    public const
        AI_MODELS = "artificial_intelligence.models",
        AI_HISTORY = "artificial_intelligence.history",
        AI_PARAMETERS = "artificial_intelligence.parameters",
        AI_CURRENCIES = "artificial_intelligence.currencies";
}

class AIProperties
{
    public const WORD_TO_TOKEN = 0.75;
}