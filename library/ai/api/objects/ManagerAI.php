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

                if ($model->exists) {
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
            AIDatabaseTable::AI_TEXT_HISTORY,
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
    public function getResult(int|string $hash, array $parameters, int $timeoutSeconds = 0): array
    {
        if (sizeof($this->models) === 1) {
            $model = $this->models[0];
        } else {
            $model = null;
            $length = 0;

            foreach ($parameters["messages"] as $parameter) {
                $length += strlen($parameter["content"]);
            }
            foreach ($this->models as $rowModel) {
                if ($length <= $rowModel->context) {
                    $model = $rowModel;
                    break;
                }
            }
            if ($model === null) {
                return array(false, null, null);
            }
        }

        switch ($model->familyID) {
            case AIModelFamily::CHAT_GPT_3_5:
            case AIModelFamily::CHAT_GPT_4:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
                $link = "https://api.openai.com/v1/chat/completions";
                $parameters["model"] = $model->code;

                if (!empty($this->parameters)) {
                    foreach ($this->parameters as $key => $value) {
                        if ($value !== null) {
                            $parameters[$key] = $value;
                        }
                    }
                }
                break;
            default:
                $link = null;
                break;
        }

        if ($link !== null) {
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
                    $link,
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
                    $reply = json_decode($reply);

                    if (is_object($reply)) {
                        if (isset($reply->usage->prompt_tokens)
                            && isset($reply->usage->completion_tokens)) {
                            sql_insert(
                                AIDatabaseTable::AI_TEXT_HISTORY,
                                array(
                                    "model_id" => $model->modelID,
                                    "hash" => $hash,
                                    "sent_parameters" => $parameters,
                                    "received_parameters" => $received,
                                    "sent_tokens" => $reply->usage->prompt_tokens,
                                    "received_tokens" => $reply->usage->completion_tokens,
                                    "currency_id" => $model->currency->id,
                                    "sent_token_cost" => ($reply->usage->prompt_tokens * $model->sent_token_cost),
                                    "received_token_cost" => ($reply->usage->completion_tokens * $model->received_token_cost),
                                    "creation_date" => get_current_date()
                                )
                            );
                            return array(true, $model, $reply);
                        } else {
                            sql_insert(
                                AIDatabaseTable::AI_TEXT_HISTORY,
                                array(
                                    "model_id" => $model->modelID,
                                    "hash" => $hash,
                                    "failure" => true,
                                    "sent_parameters" => $parameters,
                                    "received_parameters" => $received,
                                    "currency_id" => $model->currency->id,
                                    "creation_date" => get_current_date()
                                )
                            );
                            return array(false, $model, $reply);
                        }
                    }
                }

                sql_insert(
                    AIDatabaseTable::AI_TEXT_HISTORY,
                    array(
                        "model_id" => $model->modelID,
                        "hash" => $hash,
                        "failure" => true,
                        "sent_parameters" => $parameters,
                        "currency_id" => $model->currency->id,
                        "creation_date" => get_current_date()
                    )
                );
            }
        }
        return array(false, $model, null);
    }

}