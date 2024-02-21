<?php

class AccountInstructions
{

    private Account $account;
    private array
        $localInstructions,
        $publicInstructions,
        $placeholders,
        $browse,
        $replacements;
    private string
        $placeholderStart,
        $placeholderMiddle,
        $placeholderEnd;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->placeholderStart = InformationPlaceholder::STARTER;
        $this->placeholderMiddle = InformationPlaceholder::DIVISOR_REPLACEMENT;
        $this->placeholderEnd = InformationPlaceholder::ENDER;
        set_sql_cache(null, self::class);
        $this->localInstructions = get_sql_query(
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
        );
        set_sql_cache(null, self::class);
        $this->publicInstructions = get_sql_query(
            InstructionsTable::PUBLIC,
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
        );
        set_sql_cache(null, self::class);
        $this->placeholders = get_sql_query(
            InstructionsTable::PLACEHOLDERS,
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
            )
        );
        set_sql_cache(null, self::class);
        $this->replacements = get_sql_query(
            InstructionsTable::REPLACEMENTS,
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
            )
        );
        //set_sql_cache(null, self::class); // Bot does not load with it for some reason
        $this->browse = get_sql_query(
            InstructionsTable::BROWSE,
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
            )
        );

        if (!empty($this->browse)) {
            foreach ($this->browse as $arrayKey => $arrayValue) {
                if ($arrayValue->contains === null) {
                    $arrayValue->contains = array();
                } else {
                    $arrayValue->contains = explode("|", $arrayValue->contains);
                }
                $this->browse[$arrayKey] = $arrayValue;
            }
        }
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
                            bool    $local = false): array
    {
        if ($object !== null && !empty($this->placeholders)) {
            $placeholders = $this->placeholders;

            foreach ($placeholders as $arrayKey => $placeholder) {
                if (isset($object->{$placeholder->placeholder})) {
                    unset($placeholders[$arrayKey]);
                    $value = $local ? $object->{$placeholder->placeholder}
                        : $this->prepareValue(
                            $object->{$placeholder->placeholder},
                            $object,
                            $dynamicPlaceholders,
                            $recursive
                        );

                    foreach ($messages as $messageKey => $message) {
                        if (!empty($message)) {
                            $messages[$messageKey] = str_replace(
                                $this->placeholderStart . $placeholder->placeholder . $this->placeholderEnd,
                                $value,
                                $message
                            );
                        }
                    }
                } else if ($placeholder->dynamic === null) {
                    unset($placeholders[$arrayKey]);
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
                                    $found = false;

                                    foreach ($placeholders as $placeholder) {
                                        if ($placeholder->placeholder == $keyWord) {
                                            $found = true;
                                            break;
                                        }
                                    }

                                    if (!$found) {
                                        continue;
                                    }
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
                                                } catch (Throwable $e) {
                                                    $value = "";
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
                                                } catch (Throwable $e) {
                                                    $value = "";
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
        }
        return $messages;
    }

    private function prepareValue(mixed $value, object $object, array $dynamicPlaceholders, bool $recursive): mixed
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
        } else {
            $value = $this->replace(
                array($value),
                $object,
                $dynamicPlaceholders,
                false,
                true
            )[0];
        }
        return $value;
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

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getLocal(?string $userInput = null): array
    {
        if (!empty($this->localInstructions)) {
            $array = $this->localInstructions;

            foreach ($array as $value) {
                if ($value->information !== null) {
                    $value->information = $this->prepareRow($value, $value->information, $userInput);
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
            $hasSpecific = $allow !== null;

            foreach ($array as $arrayKey => $row) {
                if ($hasSpecific ? in_array($row->id, $allow) : $row->default_use !== null) {
                    $doc = $this->getURLData($row, InstructionsTable::PUBLIC);

                    if ($doc !== null) {
                        $array[$arrayKey] = $this->prepareRow($row, $doc, $userInput);
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