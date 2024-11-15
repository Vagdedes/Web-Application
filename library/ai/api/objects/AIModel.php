<?php

class AIModel
{
    private int $typeID, $familyID, $modelID;
    private ?int $context;
    private string $requestUrl, $codeKey, $code;
    private object $parameter, $currency;
    private ?float $received_token_cost, $sent_token_cost;
    private bool $exists;
    private array $pricing;

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
                    $pricing = get_sql_query(
                        AIDatabaseTable::AI_PRICING,
                        null,
                        array(
                            array("id", $modelID),
                            array("deletion_date", null)
                        )
                    );

                    if (!empty($pricing)) {
                        foreach ($pricing as $item) {
                            if (array_key_exists($item->parameter_family, $pricing)) {
                                $pricing[$item->parameter_family][] = $item;
                            } else {
                                $pricing[$item->parameter_family] = array($item);
                            }
                        }
                        $this->pricing = $pricing;
                    } else {
                        $this->pricing = array();
                    }
                } else {
                    $this->exists = false;
                    $this->pricing = array();
                }
            } else {
                $this->exists = false;
                $this->pricing = array();
            }
        } else {
            $this->exists = false;
            $this->pricing = array();
        }
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    // Separator

    public function getReceivedTokenCost(): float
    {
        return $this->received_token_cost;
    }

    public function getSentTokenCost(): float
    {
        return $this->sent_token_cost;
    }

    // Separator

    public function getCurrency(): object
    {
        return $this->currency;
    }

    public function getParameter(): object
    {
        return $this->parameter;
    }

    // Separator

    public function getFamilyID(): int
    {
        return $this->familyID;
    }

    public function getTypeID(): int
    {
        return $this->typeID;
    }

    public function getModelID(): int
    {
        return $this->modelID;
    }

    // Separator

    public function getContext(): ?int
    {
        return $this->context;
    }

    // Separator

    public function getRequestURL(): string
    {
        return $this->requestUrl;
    }

    public function getCodeKey(): string
    {
        return $this->codeKey;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    // Separator

    public function getText(?object $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
                return $object?->choices[0]?->message?->content;
            case AIModelFamily::OPENAI_WHISPER:
                return $object?->text;
            default:
                return null;
        }
    }

    public function getTexts(mixed $object): array
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
                $array = $object?->choices;
                $texts = array();

                if (!empty($array)) {
                    foreach ($array as $item) {
                        $texts[] = $item?->message?->content;
                    }
                }
                return $texts;
            case AIModelFamily::OPENAI_WHISPER:
                return array(
                    $object?->text
                );
            default:
                return array();
        }
    }

    public function getImage(mixed $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::DALLE_3:
                return $object?->data[0]?->url;
            default:
                return null;
        }
    }

    public function getImages(mixed $object): array
    {
        switch ($this->familyID) {
            case AIModelFamily::DALLE_3:
                $array = $object?->data;
                $images = array();

                if (!empty($array)) {
                    foreach ($array as $item) {
                        $images[] = $item->url;
                    }
                }
                return $images;
            default:
                return array();
        }
    }

    public function getSpeech(mixed $object): mixed
    {
        switch ($this->familyID) {
            case AIModelFamily::OPENAI_TTS:
            case AIModelFamily::OPENAI_TTS_HD:
                return $object;
            default:
                return null;
        }
    }

    public function getCost(mixed $object): ?float
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
                return ($object->usage->prompt_tokens * ($this?->sent_token_cost ?? 0.0))
                    + ($object->usage->completion_tokens * ($this?->received_token_cost ?? 0.0));
            case AIModelFamily::DALLE_3:
                if ($object instanceof ManagerAI
                    && !empty($this->pricing)) {
                    $parameters = $object->getAllParameters();

                    if (!empty($parameters)) {
                        foreach ($this->pricing as $family) {
                            $price = null;

                            foreach ($family as $row) {
                                foreach ($parameters as $key => $value) {
                                    if ($row->parameter_name == $key
                                        && $row->parameter_match == $value) {
                                        $price = $row->price;
                                    } else {
                                        $price = null;
                                        continue 2;
                                    }
                                }
                            }

                            if ($price !== null) {
                                return $price;
                            }
                        }
                    }
                }
                return null;
            case AIModelFamily::OPENAI_TTS:
            case AIModelFamily::OPENAI_TTS_HD:
                if ($object instanceof ManagerAI) {
                    return strlen($object->getAllParameters()["input"] ?? "") *
                        ($this?->sent_token_cost ?? 0.0);
                } else {
                    return null;
                }
            case AIModelFamily::OPENAI_WHISPER:
                return null; // todo
            default:
                return null;
        }
    }

    private static function getMp3SecondsDuration($filePath): ?int
    {
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze($filePath);

        if (isset($fileInfo['playtime_seconds'])) {
            return round($fileInfo['playtime_seconds']);
        } else {
            return null;
        }
    }

}