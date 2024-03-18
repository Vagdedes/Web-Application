<?php

class AccountInstructions
{

    private Account $account;
    private array
        $localInstructions,
        $publicInstructions,
        $browse,
        $replacements,
        $extra;
    private string
        $placeholderStart,
        $placeholderMiddle,
        $placeholderEnd;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->extra = array();
        $this->placeholderStart = InformationPlaceholder::STARTER;
        $this->placeholderMiddle = InformationPlaceholder::DIVISOR_REPLACEMENT;
        $this->placeholderEnd = InformationPlaceholder::ENDER;
        $this->localInstructions = $this->calculateContains(get_sql_query(
            InstructionsTable::LOCAL,
            null,
            array(
                array("deletion_date", null),
                null,
                array("application_id", "IS", null, 0),
                array("application_id", $this->account->getDetail("application_id")),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "priority DESC"
        ));
        $this->publicInstructions = $this->calculateContains(get_sql_query(
            InstructionsTable::PUBLIC,
            null,
            array(
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id")),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "priority DESC"
        ));
        $this->browse = $this->calculateContains(get_sql_query(
            InstructionsTable::BROWSE,
            null,
            array(
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id")),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        ));

        set_sql_cache(null, self::class);
        $this->replacements = get_sql_query(
            InstructionsTable::REPLACEMENTS,
            null,
            array(
                array("deletion_date", null),
                array("application_id", $this->account->getDetail("application_id")),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    private function calculateContains(array $array): array
    {
        if (!empty($array)) {
            foreach ($array as $arrayKey => $row) {
                if ($row->contains === null) {
                    $row->contains = array();
                } else {
                    $row->contains = explode("|", $row->contains);
                }
                $array[$arrayKey] = $row;
            }
        }
        return $array;
    }

    // Separator

    public function addExtra(string $key, mixed $value): void
    {
        if (is_object($value) || is_array($value)) {
            $this->extra[$key] = "Start of '$key':\n"
                . json_encode($value)
                . "\nEnd of '$key'";
        } else {
            $this->extra[$key] = $value;
        }
    }

    public function removeExtra(string $key): void
    {
        unset($this->extra[$key]);
    }

    public function getExtra($character = "\n"): string
    {
        return implode($character, $this->extra);
    }

    // Separator

    public function setPlaceholderStart(string $placeholderStart): void
    {
        $this->placeholderStart = $placeholderStart;
    }

    public function setPlaceholderMiddle(string $placeholderMiddle): void
    {
        $this->placeholderMiddle = $placeholderMiddle;
    }

    public function setPlaceholderEnd(string $placeholderEnd): void
    {
        $this->placeholderEnd = $placeholderEnd;
    }

    // Separator

    public function replace(array   $messages,
                            ?object $object,
                            ?array  $dynamicPlaceholders,
                            bool    $recursive = true,
                            bool    $local = false,
                            bool    $debug = false): array
    {
        if ($object !== null) {
            foreach ($object as $objectKey => $objectValue) {
                if ($local) {
                    if (!is_string($objectValue)) {
                        continue;
                    }
                    $value = $objectValue;
                } else {
                    $value = $this->prepareValue(
                        $objectValue,
                        $object,
                        $dynamicPlaceholders,
                        $recursive
                    );

                    if ($value === null) {
                        continue;
                    }
                }

                foreach ($messages as $messageKey => $message) {
                    if (!empty($message)) {
                        $messages[$messageKey] = str_replace(
                            $this->placeholderStart . $objectKey . $this->placeholderEnd,
                            $value,
                            $message
                        );
                    }
                }
            }
        }

        if (!empty($dynamicPlaceholders)) {
            foreach ($messages as $messageKey => $message) {
                if (!empty($message)) {
                    $position = strpos($message, $this->placeholderStart);

                    while ($position !== false) {
                        $word = substr($message, $position);
                        $finalPosition = strpos($word, $this->placeholderEnd);

                        if ($finalPosition !== false) {
                            $word = substr($word, 0, $finalPosition + strlen($this->placeholderEnd));
                            $validWord = substr($word, strlen($this->placeholderStart), -strlen($this->placeholderEnd));
                            $explode = explode($this->placeholderMiddle, $validWord);
                            $keyWord = array_shift($explode);

                            if (array_key_exists($keyWord, $dynamicPlaceholders)) {
                                $keyWordMethod = $dynamicPlaceholders[$keyWord];

                                if (is_array($keyWordMethod)) {
                                    switch (sizeof($keyWordMethod)) {
                                        case 2: // Static
                                            try {
                                                $value = $this->prepareValue(
                                                    call_user_func_array(
                                                        $keyWordMethod,
                                                        $explode
                                                    ),
                                                    $object,
                                                    array(),
                                                    $recursive
                                                );

                                                if ($value === null) {
                                                    $value = "";
                                                }
                                            } catch (Throwable $e) {
                                                $value = "";

                                                if ($debug) {
                                                    var_dump($e->getMessage(), $e->getLine());
                                                }
                                            }
                                            break;
                                        case 3: // Object
                                            $parameters = array_pop($keyWordMethod);

                                            if (!empty($parameters)) {
                                                foreach ($parameters as $arrayKey => $parameter) {
                                                    if (is_object($parameter)
                                                        && empty(get_object_vars($parameter))
                                                        && !empty($explode)) {
                                                        $parameters[$arrayKey] = array_shift($explode);
                                                    }
                                                }
                                            }
                                            try {
                                                $value = $this->prepareValue(
                                                    call_user_func_array(
                                                        $keyWordMethod,
                                                        $parameters
                                                    ),
                                                    $object,
                                                    array(),
                                                    $recursive
                                                );

                                                if ($value === null) {
                                                    $value = "";
                                                }
                                            } catch (Throwable $e) {
                                                $value = "";

                                                if ($debug) {
                                                    var_dump($e->getMessage(), $e->getLine());
                                                }
                                            }
                                            break;
                                        default:
                                            $value = "";
                                            break;
                                    }
                                } else {
                                    try { // Static with no arguments
                                        $value = $this->prepareValue(
                                            call_user_func_array(
                                                $keyWord,
                                                $explode
                                            ),
                                            $object,
                                            array(),
                                            $recursive
                                        );

                                        if ($value === null) {
                                            $value = "";
                                        }
                                    } catch (Throwable $e) {
                                        $value = "";
                                    }
                                }
                            } else {
                                $value = "";
                            }
                            $message = str_replace($word, $value, $message);
                        } else {
                            break;
                        }
                    }
                    $messages[$messageKey] = $message;
                }
            }
        }
        return $messages;
    }

    private function prepareValue(mixed $value, ?object $object, array $dynamicPlaceholders, bool $recursive): ?string
    {
        if (is_array($value)) {
            $array = $value;
            $size = sizeof($array) - 1;
            $value = "";

            foreach ($array as $arrayKey => $row) {
                if ($recursive) {
                    $value .= $this->replace(
                        array($row),
                        $object,
                        $dynamicPlaceholders,
                        false,
                        true
                    )[0];
                } else {
                    $value .= $row;
                }

                if ($arrayKey !== $size) {
                    $value .= "\n";
                }
            }
            return $value;
        } else if (is_string($value)) {
            return $this->replace(
                array($value),
                $object,
                $dynamicPlaceholders,
                false,
                true
            )[0];
        } else {
            return null;
        }
    }

    private function prepareRow(object $row, string $data, ?string $userInput = null): string
    {
        if ($row->browse !== null && !empty($this->browse)) {
            foreach ($this->browse as $browse) {
                if (str_contains($data, $browse->information_url)) {
                    if ($userInput === null || empty($browse->contains)) {
                        $continue = true;
                    } else {
                        $continue = false;

                        foreach ($browse->contains as $contains) {
                            if (str_contains($userInput, $contains)) {
                                $continue = true;
                                break;
                            }
                        }
                    }

                    if ($continue) {
                        $url = $this->getURLData($browse, InstructionsTable::BROWSE);

                        if ($url !== null) {
                            $data .= ($browse->prefix ?? "")
                                . "Start of '" . $browse->information_url . "':\n"
                                . $url
                                . "\nEnd of '" . $browse->information_url . "'"
                                . ($browse->suffix ?? "");
                        }
                    }
                }
            }
        }
        return $data;
    }

    private function getURLData(object $row, string $table): ?string
    {
        if ($row->information_expiration !== null
            && $row->information_expiration > get_current_date()) {
            return $row->information_value;
        } else {
            $url = $row->information_url;

            if (get_domain_from_url($url) == "docs.google.com") {
                $doc = get_raw_google_doc($url);
            } else {
                $doc = get_raw_google_doc($url);

                if ($doc === null) {
                    $doc = timed_file_get_contents($url);
                }
            }
            if (is_string($doc)) {
                if ($row->replace !== null && !empty($this->replacements)) {
                    foreach ($this->replacements as $replace) {
                        $doc = str_replace(
                            $replace->find,
                            $replace->replacement,
                            $doc
                        );
                    }
                }
                set_sql_query(
                    $table,
                    array(
                        "information_value" => $doc,
                        "information_expiration" => get_future_date($row->information_duration)
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                );
                return $doc;
            }
        }
        return null;
    }

    // Separator

    public function getLocal(?array $allow = null, ?string $userInput = null): array
    {
        if (!empty($this->localInstructions)) {
            $hasSpecific = !empty($allow);
            $array = $this->localInstructions;

            foreach ($array as $arrayKey => $row) {
                if ($hasSpecific ? in_array($row->id, $allow) : $row->default_use !== null) {
                    if ($row->information !== null) { // Could be just the disclaimer
                        if ($userInput === null || empty($array->contains)) {
                            $continue = true;
                        } else {
                            $continue = false;

                            foreach ($array->contains as $contains) {
                                if (str_contains($userInput, $contains)) {
                                    $continue = true;
                                    break;
                                }
                            }
                        }

                        if ($continue) {
                            $row->information = $this->prepareRow($row, $row->information, $userInput);
                        }
                    }
                } else {
                    unset($array[$arrayKey]);
                }
            }
            return $array;
        } else {
            return array();
        }
    }

    public function getPublic(?array $allow = null, ?string $userInput = null): array
    {
        $array = $this->publicInstructions;

        if (!empty($array)) {
            $hasSpecific = !empty($allow);

            foreach ($array as $arrayKey => $row) {
                if ($hasSpecific ? in_array($row->id, $allow) : $row->default_use !== null) {
                    $doc = $this->getURLData($row, InstructionsTable::PUBLIC);

                    if ($doc !== null) {
                        if ($userInput === null || empty($array->contains)) {
                            $continue = true;
                        } else {
                            $continue = false;

                            foreach ($array->contains as $contains) {
                                if (str_contains($userInput, $contains)) {
                                    $continue = true;
                                    break;
                                }
                            }
                        }

                        if ($continue) {
                            $array[$arrayKey] = $this->prepareRow($row, $doc, $userInput);
                        }
                    } else {
                        unset($array[$arrayKey]);
                    }
                } else {
                    unset($array[$arrayKey]);
                }
            }
        }
        return $array;
    }
}