<?php

class ManagerAI
{
    private array $models, $parameters;
    private string $apiKey;

    public function __construct(int|string $modelFamily, string $apiKey, array $parameters = [])
    {
        $query = get_sql_query(
            AIDatabaseTable::AI_MODELS,
            array("id"),
            array(
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
                    $this->models[$model->getContext()] = $model;
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

    // 1: Success, 2: Model, 3: Reply
    public function getResult(int|string $hash, array $parameters = [], int $length = 0, int $timeoutSeconds = 0): array
    {
        if (!empty($this->models)) {
            $model = null;

            foreach ($this->models as $rowModel) {
                if ($length <= 0
                    || $rowModel->getContext() === null
                    || $length <= $rowModel->getContext()) {
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

                if (is_object($reply)) {
                    sql_insert(
                        AIDatabaseTable::AI_HISTORY,
                        array(
                            "model_id" => $model->getModelID(),
                            "hash" => $hash,
                            "sent_parameters" => $parameters,
                            "received_parameters" => $received,
                            "currency_id" => $model->getCurrency()?->id,
                            "creation_date" => get_current_date()
                        )
                    );
                    return array(true, $model, $reply);
                }
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
        return array(false, $model, null);
    }

}