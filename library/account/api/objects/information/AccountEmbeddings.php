<?php

class AccountEmbeddings
{

    private const AI_HASH = 581928704;

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

    public function getLastQueryId(): ?int
    {
        return $this->lastQueryId;
    }

    public function getLastCost(): ?float
    {
        return $this->lastCost;
    }

    public function getLastCurrency(): ?int
    {
        return $this->lastCurrency;
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
        $isArray = is_array($textOrArray);
        $hash = $isArray
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
                $expiration === null ? null : get_future_date($expiration),
                $isArray
            );
        } else {
            $results = unpack("f*", $query[0]->objectified);

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
        ?string      $expiration,
        bool         $isArray
    )
    {
        $managerAI = new AIManager(
            $model,
            AIHelper::getAuthorization(AIAuthorization::OPENAI)
        );
        if ($isArray) {
            $referenceArray = $this->deduplicateWithReferences($textOrArray);
            $textOrArray = $referenceArray[1];
            $referenceArray = $referenceArray[0];
        } else {
            $referenceArray = null;
        }
        $arguments = array(
            "input" => $textOrArray
        );
        if ($loop === null) {
            $outcome = $managerAI->getResult(
                self::AI_HASH,
                $arguments
            );
            return $this->processResult(
                $managerAI,
                $outcome,
                $model,
                $textOrArray,
                $hash,
                $save,
                $date,
                $expiration,
                $referenceArray
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
                    $model,
                    $textOrArray,
                    $hash,
                    $save,
                    $date,
                    $expiration,
                    $referenceArray
                ) {
                    return $this->processResult(
                        $managerAI,
                        $outcome,
                        $model,
                        $textOrArray,
                        $hash,
                        $save,
                        $date,
                        $expiration,
                        $referenceArray
                    );
                },
                function (Throwable $e) {
                    throw $e;
                }
            );
        }
    }

    private function deduplicateWithReferences(array $array): array
    {
        $valueToUniqueIndex = [];
        $unique = [];
        $refs = [];

        foreach ($array as $i => $value) {
            $key = (string)$value;
            $valueToUniqueIndexValue = $valueToUniqueIndex[$key] ?? null;

            if ($valueToUniqueIndexValue !== null) {
                $refs[$i] = $valueToUniqueIndexValue;
            } else {
                $uIndex = sizeof($unique);
                $valueToUniqueIndex[$key] = $uIndex;
                $unique[] = $value;
                $refs[$i] = $uIndex;
            }
        }
        return [$refs, $unique];
    }

    private function processResult(
        AIManager    $managerAI,
        array        $outcome,
        string       $model,
        string|array $textOrArray,
        string       $hash,
        bool         $save,
        string       $date,
        ?string      $expiration,
        ?array       $referenceArray
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
            if ($referenceArray !== null) {
                $restored = [];
                $refVals = array_values($referenceArray);

                foreach ($refVals as $pos => $uniqueIndex) {
                    if (!is_int($uniqueIndex)) {
                        throw new InvalidArgumentException("Reference at position {$pos} is not an int.");
                    }
                    if (!array_key_exists($uniqueIndex, $embeddings)) {
                        throw new InvalidArgumentException("Unique index {$uniqueIndex} not present in embeddings.");
                    }
                    $restored[$pos] = $embeddings[$uniqueIndex];
                }
                $embeddings = $restored;
            }
            $this->lastCost = $outcome[0]->getCost($outcome[1]);
            $this->lastCurrency = $outcome[0]->getCurrency()?->id;
            $this->lastQueryId = $managerAI->getLastId();

            if ($save) {
                sql_insert(
                    AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
                    array(
                        "embedding_hash" => $hash,
                        "embedding_model" => $model,
                        "objectified" => pack("f*", $embeddings),
                        "creation_date" => $date,
                        "expiration_date" => $expiration,
                        "actual" => (is_string($textOrArray)
                            ? $textOrArray
                            : pack("f*", $textOrArray))
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
        $len = count($vecA);

        if ($len !== count($vecB)) {
            return 0.0;
        }
        $dot = 0.0;

        foreach ($vecA as $i => $valA) {
            $dot += $valA * $vecB[$i];
        }
        return $dot;
    }

    public function cosineSimilarityString(string $vecA, string $vecB): float
    {
        $arrayA = unpack("f*", $vecA);
        $arrayB = unpack("f*", $vecB);
        $return = $this->cosineSimilarity($arrayA, $arrayB);
        unset($arrayA, $arrayB);
        return $return;
    }

    public function fullCosineSimilarity(array $vecA, array $vecB): float
    {
        $len = count($vecA);

        if ($len !== count($vecB)) {
            return 0.0;
        }
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vecA as $i => $valA) {
            $dotProduct += $valA * $vecB[$i];
            $normA += $valA * $valA;
            $normB += $vecB[$i] * $vecB[$i];
        }
        $denom = sqrt($normA) * sqrt($normB);
        return $denom == 0.0 ? 0.0 : $dotProduct / $denom;
    }

    public function fullCosineSimilarityString(string $vecA, string $vecB): float
    {
        $arrayA = unpack("f*", $vecA);
        $arrayB = unpack("f*", $vecB);
        $return = $this->fullCosineSimilarity($arrayA, $arrayB);
        unset($arrayA, $arrayB);
        return $return;
    }

}
