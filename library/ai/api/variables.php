<?php

class AIField
{
    public const
        INTEGER = array("type" => 1, "name" => "32-bit integer (nullable)"),
        STRING = array("type" => 2, "name" => "string (nullable)"),
        DECIMAL = array("type" => 3, "name" => "64-bit double (nullable)"),
        BOOLEAN = array("type" => 4, "name" => "boolean (nullable)"),
        ARRAY = array("type" => 5, "name" => "raw JSON format (nullable)"),
        OBJECT = array("type" => 6, "name" => "raw JSON format (nullable)");
}

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

        SPEECH_PROMPT_TO_TEXT_OR_AUDIO = 6;
}

class AIModelFamily
{
    // Text to Text
    public const
        CHAT_GPT = 1,
        CHAT_GPT_PRO = 2,
        OPENAI_O1 = 3,
        OPENAI_O1_MINI = 4,
        OPENAI_O3_MINI = 14;

    // Text to Image
    public const
        DALL_E_3 = 5,
        DALL_E_2 = 12;

    // Text to Speech
    public const
        OPENAI_TTS = 6,
        OPENAI_TTS_HD = 11;

    // Speech to Text
    public const
        OPENAI_WHISPER = 7;

    // Image And Text to Text
    public const
        OPENAI_VISION_PRO = 8,
        OPENAI_VISION = 9;

    // Speech Prompt to Text
    public const
        OPENAI_SOUND_PRO = 10,
        OPENAI_SOUND = 13;
}

class AITextCreationModelFamily
{
    public const
        MOST_POWERFUL = AIModelFamily::OPENAI_O3_MINI,
        BEST_PRICE_TO_PERFORMANCE = AIModelFamily::OPENAI_O3_MINI;
}

class AIImageReadingModelFamily
{
    public const
        MOST_POWERFUL = AIModelFamily::OPENAI_O1,
        BEST_PRICE_TO_PERFORMANCE = AIModelFamily::OPENAI_VISION_PRO;
}

class AIImageCreationModelFamily
{
    public const
        MOST_POWERFUL = AIModelFamily::DALL_E_3,
        BEST_PRICE_TO_PERFORMANCE = AIModelFamily::DALL_E_3;
}

class AISoundReadingModelFamily
{
    public const
        MOST_POWERFUL = AIModelFamily::OPENAI_SOUND_PRO,
        BEST_PRICE_TO_PERFORMANCE = AIModelFamily::OPENAI_SOUND;
}

class AISoundCreationModelFamily
{
    public const
        MOST_POWERFUL = AIModelFamily::OPENAI_SOUND_PRO,
        BEST_PRICE_TO_PERFORMANCE = AIModelFamily::OPENAI_SOUND;
}

class AICurrency
{
    public const USD = 1;
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
        AI_PARAMETERS = "artificial_intelligence.parameters",
        AI_CURRENCIES = "artificial_intelligence.currencies",
        AI_PRICING = "artificial_intelligence.pricing";
}

class AIProperties
{
    public const WORD_TO_TOKEN = 0.75;
}