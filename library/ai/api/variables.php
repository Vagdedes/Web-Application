<?php

class AIParameterType
{
    public const
        JSON = 1,
        MULTIPART_FORM_DATA = 2;
}

class AIModelType
{
    public const
        TEXT_TO_TEXT = 1,

        TEXT_TO_IMAGE = 2,
        IMAGE_AND_TEXT_TO_TEXT = 5,

        TEXT_TO_SPEECH = 3,
        SPEECH_TO_TEXT = 4,

        SPEECH_PROMPT_TO_TEXT = 6;
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
        OPENAI_TTS = 6,
        OPENAI_TTS_HD = 11;

    // Speech to Text
    public const
        OPENAI_WHISPER = 7;

    // Image to Text
    public const
        OPENAI_VISION_PRO = 8,
        OPENAI_VISION = 9;

    // Speech Prompt to Text
    public const
        OPENAI_SOUND = 10;
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