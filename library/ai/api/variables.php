<?php

class AIParameterType
{
    public const JSON = 1;
}

class AIModelType
{
    public const
        TEXT = 1,
        IMAGE = 2,
        SOUND = 3;
}

class AIModelFamily
{
    public const
        CHAT_GPT_3_5 = 1,
        CHAT_GPT_4 = 2,
        OPENAI_O1 = 3,
        OPENAI_O1_MINI = 4;

    public const
        DALLE_3 = 5;
}

class AICurrency
{
    public const USD = 1;
}

class AIDatabaseTable
{
    public const
        AI_MODELS = "artificial_intelligence.models",
        AI_TEXT_HISTORY = "artificial_intelligence.history",
        AI_PARAMETERS = "artificial_intelligence.parameters",
        AI_CURRENCIES = "artificial_intelligence.currencies";
}

class AIProperties
{

    public const WORD_TO_TOKEN = 0.75;
}