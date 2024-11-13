<?php

class AIModel
{
    public int $typeID, $familyID, $modelID;
    public ?int $context;
    public string $requestUrl, $codeKey, $code;
    public object $parameter, $currency;
    private ?float $received_token_cost, $sent_token_cost;
    private bool $exists;

    public function __construct(int|string $modelID)
    {
        $query = get_sql_query(
            AIDatabaseTable::AI_MODELS,
            null,
            array(
                array("id", $modelID),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $queryChild = get_sql_query(
                AIDatabaseTable::AI_PARAMETERS,
                null,
                array(
                    array("id", $query->parameter_id),
                    array("deletion_date", null),
                ),
                null,
                1
            );

            if (!empty($queryChild)) {
                $this->parameter = $queryChild[0];

                $queryChild = get_sql_query(
                    AIDatabaseTable::AI_CURRENCIES,
                    null,
                    array(
                        array("id", $query->currency_id),
                        array("deletion_date", null),
                    ),
                    null,
                    1
                );
                if (!empty($queryChild)) {
                    $this->exists = true;
                    $this->currency = $queryChild[0];
                    $this->modelID = $query->id;
                    $this->typeID = $query->type;
                    $this->familyID = $query->family;
                    $this->context = $query->context;
                    $this->requestUrl = $query->request_url;
                    $this->codeKey = $query->code_key;
                    $this->code = $query->code;
                    $this->received_token_cost = $query->received_token_cost;
                    $this->sent_token_cost = $query->sent_token_cost;
                } else {
                    $this->exists = false;
                }
            } else {
                $this->exists = false;
            }
        } else {
            $this->exists = false;
        }
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function getText(?object $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
                return $object?->choices[0]?->message->content;
            default:
                return null;
        }
    }

    public function getImage(?object $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::DALLE_3:
                return null; // todo
            default:
                return null;
        }
    }

    public function getCost(?object $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
                return ($object->usage->prompt_tokens * ($this?->sent_token_cost ?? 0))
                    + ($object->usage->completion_tokens * ($this?->received_token_cost ?? 0));
            default:
                return null;
        }
    }

}