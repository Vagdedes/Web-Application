<?php

class AIModel
{
    private int $familyID, $modelID;
    private ?int $context;
    private string $requestUrl, $name;
    private ?string $tokenizer, $requestHeaders, $postFields;
    private object $currency;
    private ?float $received_token_cost, $received_token_audio_cost, $sent_token_cost, $sent_token_audio_cost;
    private bool $exists, $encodeFields, $base64EncodeReply;
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
                $this->requestHeaders = null;
                $this->modelID = -1;
                $this->familyID = -1;
                $this->context = null;
                $this->requestUrl = "";
                $this->postFields = null;
                $this->encodeFields = false;
                $this->tokenizer = null;
                $this->received_token_cost = null;
                $this->sent_token_cost = null;
                $this->received_token_audio_cost = null;
                $this->sent_token_audio_cost = null;
                $this->base64EncodeReply = false;
                $this->name = "";
                return;
            } else {
                $row = $query[0];
            }
        }
        $currency = AIHelper::getCurrency($row->currency_id);

        if ($currency !== null) {
            $this->exists = true;
            $this->currency = $currency;
            $this->modelID = $row->id;
            $this->familyID = $row->family;
            $this->context = $row->context;
            $this->requestUrl = $row->request_url;
            $this->requestHeaders = $row->request_headers;
            $this->postFields = $row->post_fields;
            $this->encodeFields = $row->encode_fields !== null;
            $this->tokenizer = $row->tokenizer;
            $this->received_token_cost = $row->received_token_cost;
            $this->sent_token_cost = $row->sent_token_cost;
            $this->received_token_audio_cost = $row->received_token_audio_cost;
            $this->sent_token_audio_cost = $row->sent_token_audio_cost;
            $this->base64EncodeReply = $row->base64_encode_reply !== null;
            $this->name = $row->model_name;
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
    }

    // Separator

    public function getName(): string
    {
        return $this->name;
    }

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

    // Separator

    public function getFamilyID(): int
    {
        return $this->familyID;
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

    public function getRequestHeaders(): ?array
    {
        return $this->requestHeaders === null
            ? null
            : json_decode($this->requestHeaders, true);
    }

    public function getPostFields(): ?array
    {
        return $this->postFields === null
            ? null
            : json_decode($this->postFields, true);
    }

    public function encodeFields(): bool
    {
        return $this->encodeFields;
    }

    public function base64EncodeReply(): bool
    {
        return $this->base64EncodeReply;
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
            case AIModelFamily::CHAT_GPT_NANO:
            case AIModelFamily::OPENAI_OMNI_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
            case AIModelFamily::OPENAI_SPEECH:
            case AIModelFamily::OPENAI_SPEECH_PRO:
            case AIModelFamily::OPENAI_SPEECH_OLD:
            case AIModelFamily::OPENAI_SPEECH_TEXT:
                if ($multiple) {
                    return $this->getTextsOrVoices($object);
                } else {
                    return $this->getTextOrVoice($object);
                }
            case AIModelFamily::GPT_IMAGE_1:
            case AIModelFamily::DALL_E_3:
            case AIModelFamily::DALL_E_2:
                if ($multiple) {
                    return $this->getImages($object);
                } else {
                    return $this->getImage($object);
                }
            case AIModelFamily::OPENAI_EMBEDDING_SMALL:
            case AIModelFamily::OPENAI_EMBEDDING_LARGE:
                return $this->getEmbeddings($object);
            default:
                return null;
        }
    }

    // Separator

    public function getEmbeddings(mixed $object): ?array
    {
        switch ($this->familyID) {
            case AIModelFamily::OPENAI_EMBEDDING_SMALL:
            case AIModelFamily::OPENAI_EMBEDDING_LARGE:
                if (!isset($object->data)
                    || !is_array($object->data)) {
                    return null;
                }
                $embeddings = array();

                foreach ($object->data as $item) {
                    $embeddings[$item->index ?? 0] = $item->embedding;
                }
                return $embeddings;
            default:
                return null;
        }
    }

    // Separator

    public function getTextOrVoice(mixed $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::CHAT_GPT_NANO:
            case AIModelFamily::OPENAI_OMNI_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
                return ($object?->choices[0] ?? null)?->message?->content;
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
                $content = ($object?->choices[0] ?? null)?->message?->content;

                if ($content !== null) {
                    return $content;
                } else {
                    return ($object?->choices[0] ?? null)?->message?->audio?->data;
                }
            case AIModelFamily::OPENAI_SPEECH:
            case AIModelFamily::OPENAI_SPEECH_PRO:
            case AIModelFamily::OPENAI_SPEECH_OLD:
                return $object?->text;
            case AIModelFamily::OPENAI_SPEECH_TEXT:
                return $object;
            default:
                return null;
        }
    }

    public function getTextsOrVoices(mixed $object): array
    {
        switch ($this->familyID) {
            case AIModelFamily::CHAT_GPT:
            case AIModelFamily::CHAT_GPT_PRO:
            case AIModelFamily::CHAT_GPT_NANO:
            case AIModelFamily::OPENAI_OMNI_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
                $array = $object?->choices;
                $texts = array();

                if (!empty($array)) {
                    foreach ($array as $item) {
                        $texts[] = $item?->message?->content;
                    }
                }
                return $texts;
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
                $array = $object?->choices;
                $texts = array();

                if (!empty($array)) {
                    foreach ($array as $item) {
                        $content = $item?->message?->content;

                        if ($content !== null) {
                            $texts[] = $content;
                        } else {
                            $texts[] = $item?->message?->audio?->data;
                        }
                    }
                }
                return $texts;
            case AIModelFamily::OPENAI_SPEECH:
            case AIModelFamily::OPENAI_SPEECH_PRO:
            case AIModelFamily::OPENAI_SPEECH_OLD:
                return array(
                    $object?->text
                );
            case AIModelFamily::OPENAI_SPEECH_TEXT:
                return array(
                    $object
                );
            default:
                return array();
        }
    }

    public function getImage(mixed $object): ?string
    {
        switch ($this->familyID) {
            case AIModelFamily::GPT_IMAGE_1:
                return ($object?->data[0] ?? null)?->b64_json;
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
            case AIModelFamily::GPT_IMAGE_1:
                $array = $object?->b64_json;
                $images = array();

                if (!empty($array)) {
                    foreach ($array as $item) {
                        $images[] = $item->url;
                    }
                }
                return $images;
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
            case AIModelFamily::CHAT_GPT_NANO:
            case AIModelFamily::OPENAI_OMNI_MINI:
            case AIModelFamily::OPENAI_VISION:
            case AIModelFamily::OPENAI_VISION_PRO:
            case AIModelFamily::OPENAI_SOUND:
            case AIModelFamily::OPENAI_SOUND_PRO:
            case AIModelFamily::OPENAI_EMBEDDING_SMALL:
            case AIModelFamily::OPENAI_EMBEDDING_LARGE:
                return (($object?->usage?->prompt_tokens ?? 0.0) * ($this->sent_token_cost ?? 0.0))
                    + (($object?->usage?->completion_tokens ?? 0.0) * ($this->received_token_cost ?? 0.0))

                    + (($object?->usage?->prompt_tokens_details?->reasoning_tokens ?? 0.0) * ($this->sent_token_cost ?? 0.0))
                    + (($object?->usage?->completion_tokens_details?->reasoning_tokens ?? 0.0) * ($this->received_token_cost ?? 0.0))

                    + (($object?->usage?->prompt_tokens_details?->audio_tokens ?? 0.0) * ($this->sent_token_audio_cost ?? 0.0))
                    + (($object?->usage?->completion_tokens_details?->audio_tokens ?? 0.0) * ($this->received_token_audio_cost ?? 0.0));
            case AIModelFamily::GPT_IMAGE_1:
            case AIModelFamily::DALL_E_3:
            case AIModelFamily::DALL_E_2:
                if (!($object instanceof AIManager)) {
                    if ($this->manager === null) {
                        return null;
                    }
                    $object = $this->manager;
                }
                $price = (($object?->usage?->output_tokens ?? 0.0) * ($this->received_token_cost ?? 0.0))
                    + (($object?->usage?->input_tokens ?? 0.0) * ($this->sent_token_cost ?? 0.0));

                if (false && $this->getTokenizer() !== null) { // Not used or needed but implemented nonetheless
                    $lastInput = $object->getLastInput();

                    if ($lastInput !== null) {
                        $price += AIHelper::getTokens(
                                $this->getTokenizer(),
                                $lastInput
                            ) * ($this->sent_token_cost ?? 0.0);
                    }
                }
                if (!empty($this->pricing)) {
                    $parameters = $object->getAllParameters();

                    if (!empty($parameters)) {
                        foreach ($this->pricing as $family) {
                            foreach ($family as $row) {
                                if (($parameters[$row->parameter_name] ?? null) == $row->parameter_match) {
                                    $price += $row->price;
                                    break 2;
                                } else {
                                    continue 2;
                                }
                            }
                        }
                    }
                }
                return $price;
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