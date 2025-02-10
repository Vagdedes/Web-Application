<?php

class AIManager
{

    private ?int $randomID;
    private int $typeID, $familyID;
    private array $models, $parameters, $lastParameters;
    private string $apiKey;

    public function __construct(int|AIModel $familyID,
                                string      $apiKey,
                                array       $parameters = [])
    {
        if ($familyID instanceof AIModel) {
            $familyID = $familyID->getFamilyID();
        }
        $this->typeID = -1;
        $this->familyID = $familyID;
        $this->apiKey = $apiKey;
        $this->parameters = $parameters;
        $this->lastParameters = array();
        $this->models = array();
        $this->randomID = null;

        $query = get_sql_query(
            AIDatabaseTable::AI_MODELS,
            null,
            array(
                array("family", $familyID),
                array("deletion_date", null),
            ),
            "sent_token_cost, received_token_cost DESC, context ASC"
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                if ($this->typeID === -1) {
                    $this->typeID = $row->type;
                }
                $model = new AIModel($this, $row);

                if ($model->exists()) {
                    $this->models[$model->getContext()] = $model;
                }
            }
        }
    }

    public function exists(): bool
    {
        return !empty($this->models);
    }

    public function setRandomID(int $randomID): void
    {
        $this->randomID = $randomID;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getLastParameters(): array
    {
        return $this->lastParameters;
    }

    public function getAllParameters(): array
    {
        return array_merge($this->parameters, $this->lastParameters);
    }

    public function getModels(): array
    {
        return $this->models;
    }

    public function getTypeID(): int
    {
        return $this->typeID;
    }

    public function getFamilyID(): int
    {
        return $this->familyID;
    }

    public function getHistory(int|string $hash, ?int $limit = 0): array
    {
        return get_sql_query(
            AIDatabaseTable::AI_HISTORY,
            null,
            array(
                array("hash", $hash)
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );
    }

    // 1: Success, 2: Model, 3: Reply, 4: Case
    public function getResult(int|string        $hash,
                              array             $parameters = [],
                              string|array|null $input = null,
                              int               $timeoutSeconds = 0): array
    {
        if (!empty($this->models)) {
            $model = null;

            foreach ($this->models as $rowModel) {
                if (empty($input)
                    || $rowModel->getContext() === null
                    || $rowModel->getTokenizer() === null
                    || AIHelper::getTokens($rowModel->getTokenizer(), $input) <= $rowModel->getContext()) {
                    $model = $rowModel;
                    break;
                }
            }
            if ($model === null) {
                return array(false, null, null, 0);
            }
        } else {
            return array(false, null, null, 1);
        }

        switch ($model->getParameter()?->id) {
            case AIParameterType::JSON:
                $contentType = "application/json";
                break;
            case AIParameterType::MULTIPART_FORM_DATA:
                $contentType = "multipart/form-data";
                break;
            default:
                $contentType = null;
                break;
        }

        if ($contentType !== null) {
            $this->lastParameters = $parameters;
            $parameters[$model->getCodeKey()] = $model->getCode();

            if (!empty($this->parameters)) {
                $parameters = array_merge($parameters, $this->parameters);
            }
            $parameters = @json_encode($parameters);
            $reply = get_curl(
                $model->getRequestURL(),
                "POST",
                array(
                    "Content-Type: " . $contentType,
                    "Authorization: Bearer " . $this->apiKey
                ),
                $parameters,
                $timeoutSeconds
            );

            if ($reply !== null && $reply !== false) {
                $received = $reply;
                $reply = @json_decode($reply);

                if ($reply === null) {
                    $reply = $received;
                }
                sql_insert(
                    AIDatabaseTable::AI_HISTORY,
                    array(
                        "model_id" => $model->getModelID(),
                        "hash" => $hash,
                        "random_id" => $this->randomID,
                        "sent_parameters" => $parameters,
                        "received_parameters" => $received,
                        "currency_id" => $model->getCurrency()?->id,
                        "creation_date" => get_current_date()
                    )
                );
                return array(true, $model, $reply, 2);
            }

            sql_insert(
                AIDatabaseTable::AI_HISTORY,
                array(
                    "model_id" => $model->getModelID(),
                    "hash" => $hash,
                    "sent_parameters" => $parameters,
                    "currency_id" => $model->getCurrency()?->id,
                    "creation_date" => get_current_date()
                )
            );
        }
        return array(false, $model, null, 3);
    }

}