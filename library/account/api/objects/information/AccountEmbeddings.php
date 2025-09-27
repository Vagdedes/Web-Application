<?php

class AccountEmbeddings
{

    private const AI_HASH = 581928704;

    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    private function getMemoryKey(int|float|string $hash): int
    {
        return array_to_integer(array(
            self::class,
            $hash
        ));
    }

    public function objectify(
        string  $model,
        string  $text,
        ?string $expiration = null,
        bool    $force = false,
        bool    $save = true,
        mixed   $loop = null): mixed
    {
        $model = trim($model);
        $text = trim($text);
        $hash = array_to_integer(array($model, $text), true);
        $date = get_current_date();

        if (function_exists("get_key_value_pair")) {
            $keyValue = get_key_value_pair(self::getMemoryKey($hash));

            if (is_string($keyValue)) {
                $methodReply = new MethodReply(
                    true,
                    null,
                    $keyValue
                );

                if ($loop === null) {
                    return $methodReply;
                } else {
                    return \React\Promise\resolve($methodReply);
                }
            }
        }
        $query = get_sql_query(
            AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
            array("objectified", "id"),
            array(
                array("embedding_hash", $hash),
                array("embedding_model", $model),
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
                    AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
                    array(
                        "embedding_hash" => $hash,
                        "embedding_model" => $model,
                        "actual" => $text,
                        "creation_date" => $date,
                        "expiration_date" => $expiration === null ? null : get_future_date($expiration)
                    )
                );
            }
            return $this->processObjectification(
                $model,
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
                $query->objectified
            );

            if ($query->objectified === null) {
                return $this->processObjectification(
                    $model,
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

    private function processObjectification(
        string $model,
        string $text,
        string $hash,
        bool   $save,
        mixed  $loop,
        string $date,
        ?int   $id
    )
    {
        $arguments = array(// todo
        );

        $managerAI = new AIManager(
            $model,
            AIHelper::getAuthorization(AIAuthorization::OPENAI)
        );
        if ($loop === null) {
            $outcome = $managerAI->getResult(
                self::AI_HASH,
                $arguments
            );
            return $this->processResult(
                $outcome,
                $model,
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
                    $model,
                    $hash,
                    $save,
                    $date,
                    $id
                ) {
                    return $this->processResult(
                        $outcome,
                        $model,
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
        array  $outcome,
        string $model,
        string $hash,
        bool   $save,
        string $date,
        ?int   $id
    ): MethodReply
    {
        if (array_shift($outcome)) {
            $embedding = $outcome[0]->getTextOrVoice($outcome[1]);

            if ($embedding === null) {
                return new MethodReply(
                    false,
                    null,
                    null
                );
            }
            if ($save) {
                if ($id === null) {
                    set_sql_query(
                        AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
                        array(
                            "objectified" => $embedding
                        ),
                        array(
                            array("embedding_hash", $hash),
                            array("embedding_model", $model),
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
                        AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
                        array(
                            "objectified" => $embedding
                        ),
                        array(
                            array("id", $id)
                        ),
                        null,
                        1
                    );
                }
            }
            if (function_exists("set_key_value_pair")) {
                set_key_value_pair(self::getMemoryKey($hash), $embedding, "30 minutes");
            }
            return new MethodReply(
                true,
                null,
                $embedding
            );
        } else {
            return new MethodReply(
                false,
                null,
                $outcome
            );
        }
    }

    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < sizeof($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] ** 2;
            $normB += $vecB[$i] ** 2;
        }
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

}
