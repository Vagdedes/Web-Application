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
        string       $model,
        string|array $textOrArray,
        ?string      $expiration = null,
        bool         $force = false,
        bool         $save = true,
        mixed        $loop = null): mixed
    {
        $model = trim($model);
        $hash = is_array($textOrArray)
            ? array_to_integer($textOrArray, true)
            : string_to_integer($textOrArray, true);
        $date = get_current_date();
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
            return $this->processObjectification(
                $model,
                $hash,
                $textOrArray,
                $save,
                $loop,
                $date,
                $expiration === null ? null : get_future_date($expiration)
            );
        } else {
            $results = json_decode($query[0]->objectified, true);

            if (is_array($results)) {
                $methodReply = new MethodReply(
                    true,
                    null,
                    $results
                );
            } else {
                $methodReply = new MethodReply(
                    false,
                    null,
                    null
                );
            }

            if ($loop === null) {
                return $methodReply;
            } else {
                return \React\Promise\resolve($methodReply);
            }
        }
    }

    private function processObjectification(
        string       $model,
        string       $hash,
        string|array $textOrArray,
        bool         $save,
        mixed        $loop,
        string       $date,
        ?string      $expiration
    )
    {
        $managerAI = new AIManager(
            $model,
            AIHelper::getAuthorization(AIAuthorization::OPENAI)
        );
        $arguments = array(
            "input" => $textOrArray
        );
        if ($loop === null) {
            $outcome = $managerAI->getResult(
                self::AI_HASH,
                $arguments
            );
            return $this->processResult(
                $outcome,
                $model,
                $textOrArray,
                $hash,
                $save,
                $date,
                $expiration
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
                    $textOrArray,
                    $hash,
                    $save,
                    $date,
                    $expiration
                ) {
                    return $this->processResult(
                        $outcome,
                        $model,
                        $textOrArray,
                        $hash,
                        $save,
                        $date,
                        $expiration
                    );
                },
                function (Throwable $e) {
                    throw $e;
                }
            );
        }
    }

    private function processResult(
        array        $outcome,
        string       $model,
        string|array $textOrArray,
        string       $hash,
        bool         $save,
        string       $date,
        ?string      $expiration
    ): MethodReply
    {
        if (array_shift($outcome)) {
            $embeddings = $outcome[0]->getEmbeddings($outcome[1]);

            if ($embeddings === null) {
                return new MethodReply(
                    false,
                    null,
                    null
                );
            }
            if ($save) {
                sql_insert(
                    AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
                    array(
                        "embedding_hash" => $hash,
                        "embedding_model" => $model,
                        "objectified" => json_encode($embeddings),
                        "creation_date" => $date,
                        "expiration_date" => $expiration,
                        "actual" => (is_string($textOrArray)
                            ? $textOrArray
                            : json_encode($textOrArray))
                    )
                );
            }
            return new MethodReply(
                true,
                null,
                $embeddings
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
        if (sizeof($vecA) !== sizeof($vecB)
            || sizeof($vecA) === 0) {
            return 0.0;
        }
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
