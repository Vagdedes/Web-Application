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
        bool    $save = true,
        mixed   $loop = null): mixed
    {
        $defaultLanguage = strtolower(trim($defaultLanguage));
        $language = strtolower(trim($language));

        if ($defaultLanguage === $language) {
            $methodReply = new MethodReply(
                true,
                null,
                $details ? null : $text
            );

            if ($loop === null) {
                return $methodReply;
            } else {
                return \React\Promise\resolve($methodReply);
            }
        }
        $text = trim($text);
        $hash = array_to_integer(array($language, $text), true);
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
            $details ? null : array("translation"),
            array(
                array("translation_hash", $hash),
                array("translation_language", $language),
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
                ? AIModelFamily::OPENAI_OMNI_MINI
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
            if ($loop === null) {
                $outcome = $managerAI->getResult(
                    self::AI_HASH
                );
                return $this->processResult(
                    $outcome,
                    $language,
                    $text,
                    $expiration,
                    $hash,
                    $details,
                    $force,
                    $save,
                    $date
                );
            } else {
                $outcome = $managerAI->getResult(
                    self::AI_HASH,
                    [],
                    null,
                    0,
                    $loop
                );
                return $outcome->then(
                    function (array $outcome) use (
                        $language,
                        $text,
                        $expiration,
                        $hash,
                        $details,
                        $force,
                        $save,
                        $date
                    ) {
                        return $this->processResult(
                            $outcome,
                            $language,
                            $text,
                            $expiration,
                            $hash,
                            $details,
                            $force,
                            $save,
                            $date
                        );
                    },
                    function (Throwable $e) {
                        return React\Promise\reject($e);
                    }
                );
            }
        } else {
            $query = $query[0];
            $methodReply = new MethodReply(
                true,
                null,
                $details ? $query : $query->translation
            );

            if ($loop === null) {
                return $methodReply;
            } else {
                return \React\Promise\resolve($methodReply);
            }
        }
    }

    private function processResult(
        array   $outcome,
        string  $language,
        string  $text,
        ?string $expiration,
        string  $hash,
        bool    $details,
        bool    $force,
        bool    $save,
        string  $date
    ): MethodReply
    {
        if (array_shift($outcome)) {
            $translation = $outcome[0]->getTextOrVoice($outcome[1]);

            if ($translation === null) {
                $object = new stdClass();
                $object->scenario = 2;
                $object->language = $language;
                $object->text = $text;
                $object->creation_date = $date;
                $object->expiration_date = $expiration === null ? null : get_future_date($expiration);
                $object->translation_hash = $hash;
                $object->details = $details;
                $object->force = $force;
                return new MethodReply(
                    false,
                    BigManageReader::jsonObject($object),
                    null
                );
            }
            if ($save) {
                sql_insert(
                    AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
                    array(
                        "translation_hash" => $hash,
                        "translation_language" => $language,
                        "actual" => $text,
                        "translation" => $translation,
                        "creation_date" => $date,
                        "expiration_date" => $expiration === null ? null : get_future_date($expiration)
                    )
                );
            }
            return new MethodReply(
                true,
                null,
                $translation
            );
        } else {
            $object = new stdClass();
            $object->scenario = 1;
            $object->language = $language;
            $object->text = $text;
            $object->creation_date = $date;
            $object->expiration_date = $expiration === null ? null : get_future_date($expiration);
            $object->translation_hash = $hash;
            $object->details = $details;
            $object->force = $force;
            return new MethodReply(
                false,
                BigManageReader::jsonObject($object),
                $outcome
            );
        }
    }

}
