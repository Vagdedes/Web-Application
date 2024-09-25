<?php

// $temperature: How precise or creative the reply will be. 0.0 is the most precise, 1.0 is the most creative.
// $frequency_penalty: Increase or decrease the likelihood of token repetition. -2.0 to 2.0, with -2.0 being most likely to repeat tokens, 0.0 being equally likely, and 2.0 being least likely.

class ManagerAI
{
    public array $models;
    private string $apiKey;
    private ?float $temperature, $top_p, $frequency_penalty, $presence_penalty;
    private ?int $maxTokens, $completions;
    public bool $exists;

    public function __construct(int|string $modelFamily, string $apiKey,
                                ?int       $maxReplyLength = null, ?float $temperature = null,
                                ?float     $frequency_penalty = null, ?float $presence_penalty = null,
                                ?int       $completions = null, ?float $top_p = null)
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

                if ($model->exists) {
                    $this->models[(int)$model->context] = $model;
                }
            }

            if (!empty($this->models)) {
                $this->exists = true;
                $this->apiKey = $apiKey;
                $this->temperature = $temperature;
                $this->frequency_penalty = $frequency_penalty;
                $this->completions = $completions;
                $this->top_p = $top_p;
                $this->presence_penalty = $presence_penalty;

                if ($maxReplyLength === null) {
                    $this->maxTokens = null;
                } else {
                    $maxReplyLength *= AIProperties::WORD_TO_TOKEN;
                    $maxReplyLength /= 100.0;
                    $maxReplyLength = floor($maxReplyLength);
                    $this->maxTokens = $maxReplyLength * 100;
                }
            } else {
                $this->exists = false;
            }
        } else {
            $this->exists = false;
        }
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

                if ($this->completions !== null) {
                    $parameters["n"] = $this->completions;
                }
                if ($this->maxTokens !== null) {
                    $parameters["max_tokens"] = $this->maxTokens;
                }
                if ($this->temperature !== null) {
                    $parameters["temperature"] = $this->temperature;
                }
                if ($this->frequency_penalty !== null) {
                    $parameters["frequency_penalty"] = $this->frequency_penalty;
                }
                if ($this->presence_penalty !== null) {
                    $parameters["presence_penalty"] = $this->presence_penalty;
                }
                if ($this->top_p !== null) {
                    $parameters["top_p"] = $this->top_p;
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
                $parameters = json_encode($parameters);
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

    public function getText(object $model, ?object $object): ?string
    {
        switch ($model->familyID) {
            case AIModelFamily::CHAT_GPT_3_5:
            case AIModelFamily::CHAT_GPT_4:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
                return $object?->choices[0]?->message->content;
            default:
                return null;
        }
    }

    public function getCost(object $model, ?object $object): ?string
    {
        switch ($model->familyID) {
            case AIModelFamily::CHAT_GPT_3_5:
            case AIModelFamily::CHAT_GPT_4:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
                return ($object->usage->prompt_tokens * $model->sent_token_cost)
                    + ($object->usage->completion_tokens * $model->received_token_cost);
            default:
                return null;
        }
    }
}