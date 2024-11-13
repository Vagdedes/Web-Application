<?php

class ManagerAI
{
    private array $models, $parameters;
    private string $apiKey;

    public function __construct(int|string $modelType, int|string $modelFamily, string $apiKey, array $parameters = [])
    {
        $query = get_sql_query(
            AIDatabaseTable::AI_MODELS,
            array("id"),
            array(
                array("type", $modelType),
                array("family", $modelFamily),
                array("deletion_date", null),
            ),
            "sent_token_cost, received_token_cost DESC, context ASC"
        );

        if (!empty($query)) {
            $this->models = array();

            foreach ($query as $row) {
                $model = new AIModel($row->id);

                if ($model->exists()) {
                    $this->models[(int)$model->context] = $model;
                }
            }

            if (!empty($this->models)) {
                $this->apiKey = $apiKey;
                $this->parameters = $parameters;
            }
        }
    }

    public function exists(): bool
    {
        return !empty($this->models);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getHistory(int|string $hash, ?bool $failure = null, ?int $limit = 0): array
    {
        return get_sql_query(
            AIDatabaseTable::AI_HISTORY,
            null,
            array(
                array("hash", $hash),
                $failure !== null ? array("failure", $failure) : "",
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );
    }

    // 1: Success, 2: Model, 3: Reply
    public function getResult(int|string $hash, array $parameters, int $length, int $timeoutSeconds = 0): array
    {
        if (sizeof($this->models) === 1) {
            $model = $this->models[0];

            if ($model->context !== null
                && $length > $model->context) {
                return array(false, null, null);
            }
        } else if (!empty($this->models)) {
            $model = null;

            foreach ($this->models as $rowModel) {
                if ($rowModel->context !== null
                    && $length <= $rowModel->context) {
                    $model = $rowModel;
                    break;
                }
            }
            if ($model === null) {
                return array(false, null, null);
            }
        } else {
            return array(false, null, null);
        }

        switch ($model->parameter->id) {
            case AIParameterType::JSON:
                $contentType = "application/json";
                break;
            default:
                $contentType = null;
                break;
        }

        if ($contentType !== null) {
            $parameters = @json_encode($parameters);
            $reply = get_curl(
                $model->requestUrl,
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

                if (is_object($reply)) {
                    sql_insert(
                        AIDatabaseTable::AI_HISTORY,
                        array(
                            "model_id" => $model->modelID,
                            "hash" => $hash,
                            "sent_parameters" => $parameters,
                            "received_parameters" => $received,
                            "currency_id" => $model->currency->id,
                            "creation_date" => get_current_date()
                        )
                    );
                    return array(true, $model, $reply);
                }
            }

            sql_insert(
                AIDatabaseTable::AI_HISTORY,
                array(
                    "model_id" => $model->modelID,
                    "hash" => $hash,
                    "sent_parameters" => $parameters,
                    "currency_id" => $model->currency->id,
                    "creation_date" => get_current_date()
                )
            );
        }
        return array(false, $model, null);
    }

}