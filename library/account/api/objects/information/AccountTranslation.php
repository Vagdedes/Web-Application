<?php

class AccountTranslation
{

    private const AI_HASH = 532598406;
    public const AUTO_DETECT = "auto-detect";

    private Account $account;
    private ?float $lastCost;
    private ?int $lastCurrency, $lastQueryId;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->lastCost = null;
        $this->lastCurrency = null;
        $this->lastQueryId = null;
    }

    public function getLastCost(): ?float
    {
        return $this->lastCost;
    }

    public function getLastCurrency(): ?int
    {
        return $this->lastCurrency;
    }

    public function getLastQueryId(): ?int
    {
        return $this->lastQueryId;
    }

    public function translate(
        string  $defaultLanguage,
        string  $language,
        string  $text,
        ?string $expiration = null,
        bool    $force = false,
        bool    $save = true,
        mixed   $loop = null): mixed
    {
        $text = trim($text);

        if (is_numeric($text)
            || strlen($text) < 50 && is_numeric(str_replace(" ", "", $text))) {
            return new MethodReply(
                true,
                null,
                $text
            );
        }
        $defaultLanguage = strtolower(trim($defaultLanguage));
        $language = strtolower(trim($language));

        if ($defaultLanguage === $language
            || preg_match('/^[^a-zA-Z0-9]+$/', $text)) { // Just symbols
            $methodReply = new MethodReply(
                true,
                null,
                $text
            );

            if ($loop === null) {
                return $methodReply;
            } else {
                return \React\Promise\resolve($methodReply);
            }
        }
        $hash = array_to_integer(array($language, $text), true);
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
            array("translation", "id"),
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
            if ($save) {
                sql_insert(
                    AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
                    array(
                        "translation_hash" => $hash,
                        "translation_language" => $language,
                        "actual" => $text,
                        "creation_date" => $date,
                        "expiration_date" => $expiration === null ? null : get_future_date($expiration)
                    )
                );
            }
            return $this->processTranslation(
                $language,
                $text,
                $hash,
                $save,
                $loop,
                $date,
                null
            );
        } else {
            $query = $query[0];
            $methodReply = new MethodReply(
                true,
                null,
                $query->translation
            );

            if ($query->translation === null) {
                return $this->processTranslation(
                    $language,
                    $text,
                    $hash,
                    $save,
                    $loop,
                    $date,
                    $query->id
                );
            } else if ($loop === null) {
                return $methodReply;
            } else {
                return \React\Promise\resolve($methodReply);
            }
        }
    }

    private function processTranslation(
        string $language,
        string $text,
        string $hash,
        bool   $save,
        mixed  $loop,
        string $date,
        ?int   $id
    )
    {
        $length = strlen($text);
        $modelFamily = $length > 10_000
            ? AIModelFamily::CHAT_GPT_PRO
            : ($length > 100
                ? AIModelFamily::CHAT_GPT
                : AIModelFamily::CHAT_GPT_NANO);
        $arguments = array(
            "messages" => array(
                array(
                    "role" => "system",
                    "content" => "Translate the text to the '" . $language . "' language and return only the result."
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
            AIHelper::getAuthorization(AIAuthorization::OPENAI)
        );
        if ($loop === null) {
            $outcome = $managerAI->getResult(
                self::AI_HASH,
                $arguments
            );
            return $this->processResult(
                $managerAI,
                $outcome,
                $language,
                $hash,
                $save,
                $date,
                $id
            );
        } else {
            $outcome = $managerAI->getResult(
                self::AI_HASH,
                $arguments,
                null,
                0,
                $loop
            );
            return $outcome->then(
                function (array $outcome) use (
                    $managerAI,
                    $language,
                    $hash,
                    $save,
                    $date,
                    $id
                ) {
                    return $this->processResult(
                        $managerAI,
                        $outcome,
                        $language,
                        $hash,
                        $save,
                        $date,
                        $id
                    );
                },
                function (Throwable $e) {
                    throw $e;
                }
            );
        }
    }

    private function processResult(
        AIManager $managerAI,
        array     $outcome,
        string    $language,
        string    $hash,
        bool      $save,
        string    $date,
        ?int      $id
    ): MethodReply
    {
        if (array_shift($outcome)) {
            $translation = $outcome[0]->getTextOrVoice($outcome[1]);

            if ($translation === null) {
                return new MethodReply(
                    false,
                    null,
                    null
                );
            }
            $this->lastCost = $outcome[0]->getCost($outcome[1]);
            $this->lastCurrency = $outcome[0]->getCurrency()?->id;
            $this->lastQueryId = $managerAI->getLastId();

            if ($save) {
                if ($id === null) {
                    set_sql_query(
                        AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
                        array(
                            "translation" => $translation
                        ),
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
                } else {
                    set_sql_query(
                        AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
                        array(
                            "translation" => $translation
                        ),
                        array(
                            array("id", $id)
                        ),
                        null,
                        1
                    );
                }
            }
            return new MethodReply(
                true,
                null,
                $translation
            );
        } else {
            return new MethodReply(
                false,
                null,
                $outcome
            );
        }
    }

}
