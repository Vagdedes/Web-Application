<?php

class AIModel
{
    private int $typeID, $familyID, $modelID;
    private ?int $context;
    private string $requestUrl, $codeKey, $code;
    private ?string $tokenizer;
    private object $parameter, $currency;
    private ?float $received_token_cost, $received_token_audio_cost, $sent_token_cost, $sent_token_audio_cost;
    private bool $exists;
    private array $pricing;
    private ?AIManager $manager;

    public function __construct(?AIManager $manager, int|object $row)
    {
        $this->manager = $manager;

        if (is_numeric($row)) {
            $query = get_sql_query(
                AIDatabaseTable::AI_MODELS,
                null,
                array(
                    array("family", $row),
                    array("deletion_date", null),
                ),
                "sent_token_cost, sent_token_audio_cost, received_token_cost, received_token_audio_cost DESC, context ASC",
                1
            );

            if (empty($query)) {
                $this->exists = false;
                $this->currency = new stdClass();
                $this->parameter = new stdClass();
                $this->modelID = -1;
                $this->typeID = -1;
                $this->familyID = -1;
                $this->context = null;
                $this->requestUrl = "";
                $this->codeKey = "";
                $this->code = "";
                $this->tokenizer = null;
                $this->received_token_cost = null;
                $this->sent_token_cost = null;
                $this->received_token_audio_cost = null;
                $this->sent_token_audio_cost = null;
                return;
            } else {
                $row = $query[0];
            }
        }
        $queryChild = get_sql_query(
            AIDatabaseTable::AI_PARAMETERS,
            null,
            array(
                array("id", $row->parameter_id),
                array("deletion_date", null),
            ),
            null,
            1
        );

        if (!empty($queryChild)) {
            $this->parameter = $queryChild[0];
            $currency = AIHelper::getCurrency($row->currency_id);

            if ($currency !== null) {
                $this->exists = true;
                $this->currency = $currency;
                $this->modelID = $row->id;
                $this->typeID = $row->type;
                $this->familyID = $row->family;
                $this->context = $row->context;
                $this->requestUrl = $row->request_url;
                $this->codeKey = $row->code_key;
                $this->code = $row->code;
                $this->tokenizer = $row->tokenizer;
                $this->received_token_cost = $row->received_token_cost;
                $this->sent_token_cost = $row->sent_token_cost;
                $this->received_token_audio_cost = $row->received_token_audio_cost;
                $this->sent_token_audio_cost = $row->sent_token_audio_cost;
                $pricing = get_sql_query(
                    AIDatabaseTable::AI_PRICING,
                    null,
                    array(
                        array("model_id", $row->id),
                        array("deletion_date", null)
                    )
                );

                if (!empty($pricing)) {
                    $pricingArray = array();

                    foreach ($pricing as $item) {
                        if (array_key_exists($item->parameter_family, $pricingArray)) {
                            $pricingArray[$item->parameter_family][] = $item;
                        } else {
                            $pricingArray[$item->parameter_family] = array($item);
                        }
                    }
                    $this->pricing = $pricingArray;
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
    }

    // Separator

    public function getManager(): ?AIManager
    {
        return $this->manager;
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

    public function getTokenizer(): ?string
    {
        return $this->tokenizer;
    }

    // Separator

    public function getRightAiInformation(mixed $object, bool $multiple = false): mixed
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O3_MINI:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
                if ($multiple) {
                    return $this->getTexts($object);
                } else {
                    return $this->getText($object);
                }
            case AIModelFamily::DALL_E_3:
            case AIModelFamily::DALL_E_2:
                if ($multiple) {
                    return $this->getImages($object);
                } else {
                    return $this->getImage($object);
                }
            case AIModelFamily::OPENAI_TTS:
            case AIModelFamily::OPENAI_TTS_HD:
                if ($multiple) {
                    return $this->getSpeeches($object);
                } else {
                    return $this->getSpeech($object);
                }
            default:
                return null;
        }
    }

    // Separator

    public function getText(?object $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O3_MINI:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
                return ($object?->choices[0] ?? null)?->message?->content;
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
            case AIModelFamily::OPENAI_O3_MINI:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
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
            case AIModelFamily::DALL_E_3:
            case AIModelFamily::DALL_E_2:
                return ($object?->data[0] ?? null)?->url;
            default:
                return null;
        }
    }

    public function getImages(mixed $object): array
    {
        switch ($this->familyID) {
            case AIModelFamily::DALL_E_3:
            case AIModelFamily::DALL_E_2:
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

    public function getSpeeches(mixed $object): mixed
    {
        return $this->getSpeech($object); // Not implemented by third-party company yet
    }

    public function getRevisedPrompt(mixed $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::DALL_E_3:
                return ($object?->data[0] ?? null)?->revised_prompt;
            default:
                return null;
        }
    }

    public function getRevisedPrompts(mixed $object): array
    {
        switch ($this->familyID) {
            case AIModelFamily::DALL_E_3:
                $array = $object?->data;
                $prompts = array();

                if (!empty($array)) {
                    foreach ($array as $item) {
                        $prompts[] = $item->revised_prompt;
                    }
                }
                return $prompts;
            default:
                return array();
        }
    }

    public function getCost(mixed $object): ?float
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::OPENAI_O3_MINI:
            case AIModelFamily::OPENAI_O1:
            case AIModelFamily::OPENAI_O1_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
                return (($object?->usage?->prompt_tokens ?? 0.0) * ($this->sent_token_cost ?? 0.0))
                    + (($object?->usage?->completion_tokens ?? 0.0) * ($this->received_token_cost ?? 0.0))

                    + (($object?->usage?->prompt_tokens_details?->reasoning_tokens ?? 0.0) * ($this->sent_token_cost ?? 0.0))
                    + (($object?->usage?->completion_tokens_details?->reasoning_tokens ?? 0.0) * ($this->received_token_cost ?? 0.0))

                    + (($object?->usage?->prompt_tokens_details?->audio_tokens ?? 0.0) * ($this->sent_token_audio_cost ?? 0.0))
                    + (($object?->usage?->completion_tokens_details?->audio_tokens ?? 0.0) * ($this->received_token_audio_cost ?? 0.0));
            case AIModelFamily::DALL_E_3:
            case AIModelFamily::DALL_E_2:
                if (!($object instanceof AIManager)) {
                    if ($this->manager === null) {
                        return null;
                    }
                    $object = $this->manager;
                }
                if (!empty($this->pricing)) {
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
                if ($object instanceof AIManager) {
                    return strlen($object->getAllParameters()["input"] ?? "") *
                        ($this->sent_token_cost ?? 0.0);
                } else {
                    return null;
                }
            case AIModelFamily::OPENAI_WHISPER:
                return null; // todo
            default:
                return null;
        }
    }

    private static function getMp3SecondsDuration(string $filePath): ?int
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