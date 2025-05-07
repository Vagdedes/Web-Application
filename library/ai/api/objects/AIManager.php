<?php

class AIManager
{

    private ?int $randomID;
    private int $familyID;
    private array $models, $parameters, $lastParameters;
    private string $apiKey;
    private string|array|null $lastInput;
    private ?AIModel $lastPickedModel;
    private int|string $lastHash;

    public function __construct(
        int|AIModel $familyID,
        string      $apiKey,
        array       $parameters = []
    )
    {
        if ($familyID instanceof AIModel) {
            $familyID = $familyID->getFamilyID();
        }
        $this->familyID = $familyID;
        $this->apiKey = $apiKey;
        $this->parameters = $parameters;
        $this->lastParameters = array();
        $this->models = array();
        $this->randomID = null;
        $this->lastInput = null;
        $this->lastPickedModel = null;
        $this->lastHash = 0;

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

    public function getLastInput(): string|array|null
    {
        return $this->lastInput;
    }

    public function getLastHash(): int|string
    {
        return $this->lastHash;
    }

    public function getLastPickedModel(): ?AIModel
    {
        return $this->lastPickedModel;
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
        return array_merge($this->lastParameters, $this->parameters);
    }

    public function getModels(): array
    {
        return $this->models;
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
                              int               $timeoutSeconds = 0,
                              mixed             $loop = null,
                              ?callable         $callable = null): mixed
    {
        $this->lastHash = $hash;
        $this->lastInput = $input;

        if (!empty($this->models)) {
            $this->lastPickedModel = null;

            foreach ($this->models as $rowModel) {
                if (empty($input)
                    || $rowModel->getContext() === null
                    || ($rowModel->getTokenizer() === null
                        ? (!is_string($input) || strlen($input) <= $rowModel->getContext())
                        : AIHelper::getTokens($rowModel->getTokenizer(), $input) <= $rowModel->getContext())) {
                    $this->lastPickedModel = $rowModel;
                    break;
                }
            }
            if ($this->lastPickedModel === null) {
                return array(false, null, null, 0);
            }
        } else {
            $this->lastParameters = array();
            return array(false, null, null, 1);
        }
        if (!($this->lastPickedModel instanceof AIModel)) {
            $this->lastParameters = array();
            return array(false, null, null, 4);
        }
        $headers = array(
            "Authorization: Bearer " . $this->apiKey
        );
        $requestHeaders = $this->lastPickedModel->getRequestHeaders();

        if (!empty($requestHeaders)) {
            foreach ($requestHeaders as $headerKey => $headerValue) {
                $headers[] = $headerKey . ": " . $headerValue;
            }
        }
        $postFields = $this->lastPickedModel->getPostFields();

        if (!empty($postFields)) {
            $this->lastParameters = array_merge($postFields, $parameters);
        } else {
            $this->lastParameters = $parameters;
        }
        $parameters = $this->getAllParameters();

        if ($this->lastPickedModel->encodeFields()) {
            $parameters = @json_encode($parameters);
        }
        if ($loop === null) {
            return $this->resolve(
                get_curl(
                    $this->lastPickedModel->getRequestURL(),
                    "POST",
                    $headers,
                    $parameters,
                    $timeoutSeconds
                )
            );
        } else if ($callable === null) {
            return get_react_http(
                $loop,
                $this->lastPickedModel->getRequestURL(),
                "POST",
                $headers,
                $parameters
            )->then(
                function (mixed $reply) {
                    if (class_exists("BigManageError")) {
                        BigManageError::debug($reply);
                    }
                    return $this->resolve($reply);
                },
                function (Throwable $e) {
                    return array(false, null, null, 5);
                }
            );
        } else {
            get_curl_multi_with_react(
                $loop,
                $this->lastPickedModel->getRequestURL(),
                "POST",
                $headers,
                $parameters,
                $callable,
            );
            return null;
        }
    }

    public function resolve(mixed $reply): array
    {
        if ($reply !== null && $reply !== false) {
            $received = $reply;

            if ($this->lastPickedModel->base64EncodeReply()) {
                $received = base64_encode($received);
            } else {
                $reply = @json_decode($reply);

                if ($reply === null) {
                    $reply = $received;
                }
            }
            sql_insert(
                AIDatabaseTable::AI_HISTORY,
                array(
                    "model_id" => $this->lastPickedModel->getModelID(),
                    "hash" => $this->getLastHash(),
                    "random_id" => $this->randomID,
                    "sent_parameters" => @json_encode($this->getAllParameters()),
                    "received_parameters" => $received,
                    "currency_id" => $this->lastPickedModel->getCurrency()?->id,
                    "creation_date" => get_current_date()
                )
            );
            return array(true, $this->lastPickedModel, $reply, 2);
        }

        sql_insert(
            AIDatabaseTable::AI_HISTORY,
            array(
                "model_id" => $this->lastPickedModel->getModelID(),
                "hash" => $this->getLastHash(),
                "sent_parameters" => @json_encode($this->getAllParameters()),
                "currency_id" => $this->lastPickedModel->getCurrency()?->id,
                "creation_date" => get_current_date()
            )
        );
        return array(false, $this->lastPickedModel, null, 3);
    }

}