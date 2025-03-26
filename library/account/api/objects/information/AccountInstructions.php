<?php

class AccountInstructions
{

    private const
        AI_HASH = 596802337,
        keepDatabaseKeys = [
        "priority",
        "information",
        "information_url",
        "information_value",
        "creation_reason"
    ];

    private Account $account;
    private ?array
        $localInstructions,
        $publicInstructions,
        $replacements;
    private array
        $extra,
        $deleteExtra,
        $containsCache;
    private ?AIManager $managerAI;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->extra = array();
        $this->deleteExtra = array();
        $this->containsCache = array();
        $this->replacements = array();
        $this->localInstructions = null;
        $this->publicInstructions = null;
    }

    private function cacheReplacements(): void
    {
        $this->replacements = get_sql_query(
            AccountVariables::INSTRUCTIONS_REPLACEMENTS_TABLE,
            null,
            array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function setAI(?AIManager $chatAI): void
    {
        $this->managerAI = $chatAI;
        $this->getPublic();
    }

    private function calculateRawContains(?string $text): array
    {
        return $text === null
            ? array()
            : explode("|", $text);
    }

    private function calculateContains(array $array): array
    {
        if (!empty($array)) {
            foreach ($array as $arrayKey => $row) {
                $row->contains = $this->calculateRawContains($row->contains);
                $array[$arrayKey] = $row;
            }
        }
        return $array;
    }

    // Separator

    public function buildExtra(?string $key, mixed $value): ?string
    {
        if (!empty($value)) {
            if (is_object($value) || is_array($value)) {
                if (is_object($value)) {
                    $value = clear_object_null_keys($value);
                } else {
                    $value = clear_array_null_keys($value);
                }
                if ($key === null) {
                    $json = @json_encode($value);
                } else {
                    $object = new stdClass();
                    $object->{$key} = $value;
                    $json = @json_encode($object);
                }

                if ($json !== false) {
                    return $json;
                } else {
                    return null;
                }
            } else {
                if ($key === null) {
                    $json = @json_encode($value);
                } else {
                    $object = new stdClass();
                    $object->{$key} = $value;
                    $json = @json_encode($object);
                }

                if ($json !== false) {
                    return $json;
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
    }

    public function addExtra(string $key, mixed $value, bool $delete = false, bool $checkValueForConcurrency = false): void
    {
        $extra = $this->buildExtra(null, $value);

        if ($extra !== null) {
            if ($checkValueForConcurrency && !empty($this->extra)) {
                if (in_array($extra, $this->extra)) {
                    return;
                }
            }
            $this->extra[$key] = $extra;

            if ($delete) {
                $this->deleteExtra[$key] = true;
            }
        }
    }

    public function mergeExtras(array $extra, bool $build = false): void
    {
        foreach ($extra as $key => $value) {
            if (!array_key_exists($key, $this->extra)) {
                if ($build) {
                    $value = $this->buildExtra($key, $value);
                }
                $this->extra[$key] = $value;
            }
        }
    }

    public function getExtras(): array
    {
        return $this->extra;
    }

    public function hasExtras(): bool
    {
        return !empty($this->extra);
    }

    public function hasExtra(string $key): bool
    {
        return array_key_exists($key, $this->extra);
    }

    public function removeExtra(string $key): void
    {
        unset($this->extra[$key]);
        unset($this->deleteExtra[$key]);
    }

    private function autoRemoveExtra(): void
    {
        foreach (array_keys($this->deleteExtra) as $key) {
            $this->removeExtra($key);
        }
    }

    // Separator

    private function prepare(mixed $object): string|bool
    {
        if (is_object($object)) {
            $object = clear_object_null_keys($object);
            return @json_encode($object);
        } else if (is_array($object)) {
            $object = clear_array_null_keys($object);
            return @json_encode($object);
        }
        return $object === null ? "" : $object;
    }

    private function deepReplace(mixed $mix, mixed $objectKey, mixed $objectValue, bool $preparedValue = false): mixed
    {
        if (!$preparedValue) {
            $objectValue = $this->prepare($objectValue);
        }
        if (is_object($mix)) {
            foreach ($mix as $key => $value) {
                $mix->{$key} = $this->deepReplace($value, $objectKey, $objectValue, true);
            }
            return $mix;
        } else if (is_array($mix)) {
            foreach ($mix as $key => $value) {
                $mix[$key] = $this->deepReplace($value, $objectKey, $objectValue, true);
            }
            return $mix;
        } else {
            return $mix === null
                ? ""
                : str_replace(
                    InformationPlaceholder::STARTER . $objectKey . InformationPlaceholder::ENDER,
                    $objectValue,
                    $mix
                );
        }
    }

    public function replace(array   $array,
                            ?object $object,
                            ?array  $callables,
                            bool    $extra): array
    {
        if ($object !== null) {
            foreach ($object as $objectKey => $objectValue) {
                $array = $this->deepReplace(
                    $array,
                    $objectKey,
                    $objectValue
                );
            }
        }
        if (!empty($callables)) {
            foreach ($callables as $callableKey => $callable) {
                try {
                    $callable = $callable();
                } catch (Throwable $e) {
                    $callable = null;
                    var_dump($e->getTraceAsString());
                }
                $array = $this->deepReplace(
                    $array,
                    $callableKey,
                    $callable
                );
            }
        }
        if (!empty($this->extra)) {
            if ($extra) {
                foreach ($this->extra as $key => $value) {
                    $value = $this->deepReplace(
                        $value,
                        $key,
                        $value
                    );
                    if (array_key_exists($key, $array)) {
                        $array[] = $value;
                    } else {
                        $array[$key] = $value;
                    }
                }
            }
            $this->autoRemoveExtra();
        }
        return $array;
    }

    private function getURLData(mixed $arrayKey, object $row, bool $refresh): ?string
    {
        if ($row->information_value !== null
            && $row->information_expiration !== null
            && $row->information_expiration > get_current_date()) {
            return $row->information_value;
        } else if ($refresh || $row->information_value === null) {
            $html = timed_file_get_contents($row->information_url, 5);

            if ($html !== false) {
                $doc = get_raw_google_doc($html);

                if ($doc === null) {
                    $doc = $html;
                }

                if (!empty($doc)) {
                    $containsKeywords = null;

                    if (!empty($this->replacements)) {
                        foreach ($this->replacements as $replace) {
                            $doc = str_replace(
                                $replace->find,
                                $replace->replacement,
                                $doc
                            );
                        }
                    }
                    if ($row->auto_contains !== null
                        && $this?->managerAI->exists()) {
                        $result = $this->managerAI->getResult(
                            self::AI_HASH,
                            array(
                                "messages" => array(
                                    array(
                                        "role" => "system",
                                        "content" => "From the user's text write only the " . $row->auto_contains . " most important keywords separated"
                                            . " by the | character without spaces in between and a maximum total length of"
                                            . " 4000 characters. Do not combine multiple words together, instead separate"
                                            . " them by using the | character. For example: keyword1|keyword2|keyword3"
                                    ),
                                    array(
                                        "role" => "user",
                                        "content" => $doc
                                    )
                                )
                            )
                        );

                        if ($result[0]) {
                            $containsKeywords = $result[1]->getTextOrVoice($result[2]);
                        }
                    }
                    $expiration = get_future_date($row->information_duration);
                    $containsKeywords = empty($containsKeywords)
                        ? null
                        : $row->information_url . "|" . strtolower($containsKeywords);

                    if (set_sql_query(
                        AccountVariables::INSTRUCTIONS_PUBLIC_TABLE,
                        array(
                            "information_value" => $doc,
                            "information_expiration" => $expiration,
                            "contains" => $containsKeywords
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    )) {
                        $row->information_value = $doc;
                        $row->information_expiration = $expiration;
                        $row->contains = $this->calculateRawContains($containsKeywords);
                        $this->publicInstructions[$arrayKey] = $row;
                        unset($this->containsCache[$arrayKey]);
                    }
                    return $doc;
                }
            }
            return null;
        } else {
            return $row->information_value;
        }
    }

    // Separator

    public function getLocal(?array $allow = null, ?string $userInput = null): array
    {
        if ($this->localInstructions === null) {
            $this->localInstructions = $this->calculateContains(get_sql_query(
                AccountVariables::INSTRUCTIONS_LOCAL_TABLE,
                null,
                array(
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "priority"
                )
            ));
            $this->cacheReplacements();
        }
        $array = $this->localInstructions;

        if (empty($array)) {
            return array();
        } else {
            $isArray = is_array($allow);

            foreach ($array as $arrayKey => $row) {
                if (!$isArray
                    || (sizeof($allow) === 0 ? $row->default_use !== null : in_array($row->id, $allow))) {
                    if ($userInput === null || empty($row->contains)) {
                        $continue = true;
                    } else {
                        $continue = false;

                        foreach ($row->contains as $contains) {
                            if (str_contains($userInput, $contains)) {
                                $continue = true;
                                break;
                            }
                        }
                    }

                    if ($continue) {
                        $array[$arrayKey] = $row;
                    } else {
                        unset($array[$arrayKey]);
                    }
                } else {
                    unset($array[$arrayKey]);
                }
            }

            if (empty($array)) {
                return array();
            } else {
                $new = array();

                foreach ($array as $arrayKey => $row) {
                    $newObject = new stdClass();

                    foreach ($row as $objectKey => $value) {
                        if (in_array($objectKey, self::keepDatabaseKeys)) {
                            $newObject->{$objectKey} = $value;
                        }
                    }
                    $new[$arrayKey] = $newObject;
                }
                return $new;
            }
        }
    }

    public function getPublic(?array $allow = null, ?string $userInput = null, bool $refresh = true): array
    {
        if ($this->publicInstructions === null) {
            $this->publicInstructions = $this->calculateContains(get_sql_query(
                AccountVariables::INSTRUCTIONS_PUBLIC_TABLE,
                null,
                array(
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "priority"
                )
            ));
            $this->cacheReplacements();
        }
        $array = $this->publicInstructions;

        if (empty($array)) {
            return array();
        } else {
            $isArray = is_array($allow);
            $hasUserInput = $userInput !== null;

            if ($hasUserInput) {
                $userInput = explode(" ", $userInput);
            }

            foreach ($array as $arrayKey => $row) {
                if (!$isArray
                    || (sizeof($allow) === 0 ? $row->default_use !== null : in_array($row->id, $allow))) {
                    if (!$hasUserInput || empty($row->contains)) {
                        $doc = $this->getURLData($arrayKey, $row, $refresh);
                    } else {
                        $doc = null;

                        foreach ($userInput as $input) {
                            if ($this->equals($arrayKey, $row, $input)) {
                                $doc = $this->getURLData($arrayKey, $row, $refresh);
                                break;
                            }
                        }
                    }

                    if ($doc !== null) {
                        $row->information_value = $doc;
                        $array[$arrayKey] = $row;
                    } else if ($row->information_value === null) {
                        unset($array[$arrayKey]);
                    }
                } else {
                    unset($array[$arrayKey]);
                }
            }

            if (empty($array)) {
                return array();
            } else {
                $new = array();

                foreach ($array as $arrayKey => $row) {
                    $newObject = new stdClass();

                    foreach ($row as $objectKey => $value) {
                        if (in_array($objectKey, self::keepDatabaseKeys)) {
                            $newObject->{$objectKey} = $value;
                        }
                    }
                    $new[$arrayKey] = $newObject;
                }
                return $new;
            }
        }
    }

    private function equals(mixed $arrayKey, object $row, string $word): bool
    {
        $word = strtolower($word);
        $hasKey = array_key_exists($arrayKey, $this->containsCache);

        if ($hasKey
            && in_array($word, $this->containsCache[$arrayKey])) {
            return true;
        }

        foreach ($row->contains as $contains) {
            if ($word == $contains) {
                if ($hasKey) {
                    $this->containsCache[$arrayKey][] = $word;
                } else {
                    $this->containsCache[$arrayKey] = array($word);
                }
                return true;
            }
        }
        return false;
    }
}