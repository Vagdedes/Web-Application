<?php

class AIParameterType
{
    public const JSON = 1;
}

class AIModelFamily
{
    public const
        CHAT_GPT_3_5 = 1,
        CHAT_GPT_4 = 2,
        OPENAI_O1 = 3,
        OPENAI_O1_MINI = 4;
}

class AICurrency
{
    public const EUR = 1;
}

class AIDatabaseTable
{
    public const
        AI_MODELS = "artificial_intelligence.models",
        AI_TEXT_HISTORY = "artificial_intelligence.textHistory",
        AI_PARAMETERS = "artificial_intelligence.parameters",
        AI_CURRENCIES = "artificial_intelligence.currencies";
}

class AIProperties
{

    public const
        TOKEN_TO_WORD = 1000.0 / 750.0, // 1.333333
        WORD_TO_TOKEN = 750.0 / 1000.0; // 0.75
}