<?php

class AccountTranslation
{

    private const AI_HASH = 532598406;
    public const AUTO_DETECT = "auto-detect";

    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function translate(
        string  $defaultLanguage,
        string  $language,
        string  $text,
        ?string $expiration = null,
        bool    $details = false,
        bool    $force = false,
        bool    $save = true): MethodReply
    {
        $defaultLanguage = strtolower(trim($defaultLanguage));
        $language = strtolower(trim($language));

        if ($defaultLanguage === $language) {
            return new MethodReply(
                false,
                null,
                null
            );
        }
        $text = trim($text);
        $hash = array_to_integer(array($language, $text), true);
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
            $details ? null : array("after"),
            array(
                array("translation_hash", $hash),
                array("language", $language),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if ($force || empty($query)) {
            $modelFamily = $save && $expiration === null
                ? AIModelFamily::OPENAI_O3_MINI
                : AIModelFamily::CHAT_GPT;
            $arguments = array(
                "messages" => array(
                    array(
                        "role" => "system",
                        "content" => "Translate the text to '" . $language . "' and return only the result."
                    ),
                    array(
                        "role" => "user",
                        "content" => $text
                    )
                )
            );

            if (AIHelper::isReasoningModel($modelFamily)) {
                $arguments["reasoning_effort"] = "low";
            } else {
                $arguments["temperature"] = 0.1;
            }
            $managerAI = new AIManager(
                $modelFamily,
                AIHelper::getAuthorization(AIAuthorization::OPENAI),
                $arguments
            );
            $outcome = $managerAI->getResult(
                self::AI_HASH
            );

            if (array_shift($outcome)) {
                $after = $outcome[0]->getTextOrVoice($outcome[1]);

                if ($after === null) {
                    return new MethodReply(
                        false,
                        null,
                        null
                    );
                }
                if ($save) {
                    sql_insert(
                        AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
                        array(
                            "translation_hash" => $hash,
                            "language" => $language,
                            "actual" => $text,
                            "translation" => $after,
                            "creation_date" => $date,
                            "expiration_date" => $expiration === null ? null : get_future_date($expiration)
                        )
                    );
                }
                return new MethodReply(
                    true,
                    null,
                    $after
                );
            } else {
                return new MethodReply(
                    false,
                    null,
                    $outcome
                );
            }
        } else {
            $query = $query[0];
            return new MethodReply(
                true,
                null,
                $details ? $query : $query->after
            );
        }
    }

}
