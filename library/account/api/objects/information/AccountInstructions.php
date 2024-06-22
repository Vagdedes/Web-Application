<?php

class AccountInstructions
{

    private const AI_HASH = 596802337;

    private Account $account;
    private array
        $localInstructions,
        $publicInstructions,
        $browse,
        $replacements,
        $extra,
        $deleteExtra;
    private string
        $placeholderStart,
        $placeholderMiddle,
        $placeholderEnd;
    private ?ManagerAI $managerAI;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->extra = array();
        $this->deleteExtra = array();
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

    public function setAI(?ManagerAI $chatAI): void
    {
        $this->managerAI = $chatAI;
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

    public function addExtra(string $key, mixed $value, bool $delete = false): void
    {
        if (!empty($value)) {
            if (is_object($value) || is_array($value)) {
                $json = @json_encode($value);

                if ($json !== false) {
                    $this->extra[$key] = "Start of '$key':\n"
                        . $json
                        . "\nEnd of '$key'";
                } else {
                    return;
                }
            } else {
                $this->extra[$key] = $value;
            }
            if ($delete) {
                $this->deleteExtra[$key] = true;
            }
        }
    }

    public function removeExtra(string $key): void
    {
        unset($this->extra[$key]);
        unset($this->deleteExtra[$key]);
    }

    public function removeAllExtra(): void
    {
        foreach (array_keys($this->deleteExtra) as $key) {
            $this->removeExtra($key);
        }
    }

    public function getSpecificExtra(string $key): string
    {
        $return = $this->extra[$key];

        if (array_key_exists($key, $this->deleteExtra)) {
            $this->removeExtra($key);
        }
        return $return;
    }

    public function getExtra($character = "\n"): string
    {
        $return = implode($character, $this->extra);
        $this->removeAllExtra();
        return $return;
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
                if ($browse->force_include !== null || str_contains($data, $browse->information_url)) {
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
                                . json_encode($url)
                                . "\nEnd of '" . $browse->information_url . "'"
                                . ($browse->suffix ?? "");

                            if (false && $browse->sub_directories !== null) {
                                $domain = get_domain_from_url($browse->information_url, true);
                                $links = get_urls_from_string($url);

                                if (!empty($links)) {
                                    foreach ($links as $link) {
                                        if ($link != $browse->information_url
                                            && $link != ($browse->information_url . "/")
                                            && ($browse->sub_directories == 2
                                                || get_domain_from_url($link, true) == $domain)) {
                                            $url = $this->getRawURLData($link);

                                            if ($url !== null) {
                                                $data .= ($browse->prefix ?? "")
                                                    . "Start of '" . $link . "':\n"
                                                    . json_encode($url)
                                                    . "\nEnd of '" . $link . "'"
                                                    . ($browse->suffix ?? "");
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $data;
    }

    private function getRawURLData(string $url): ?string
    {
        if (get_domain_from_url($url) == "docs.google.com") {
            return get_raw_google_doc($url);
        } else {
            $doc = get_raw_google_doc($url);

            if ($doc === null) {
                return timed_file_get_contents($url);
            } else {
                return $doc;
            }
        }
    }

    private function getURLData(object $row, string $table): ?string
    {
        if ($row->information_expiration !== null
            && $row->information_expiration > get_current_date()) {
            return $row->information_value;
        } else {
            $doc = $this->getRawURLData($row->information_url);

            if (is_string($doc)) {
                $containsKeywords = null;

                if ($row->replace !== null && !empty($this->replacements)) {
                    foreach ($this->replacements as $replace) {
                        $doc = str_replace(
                            $replace->find,
                            $replace->replacement,
                            $doc
                        );
                    }
                }
                if ($row->auto_contains !== null
                    && $this->managerAI !== null
                    && $this->managerAI->exists) {
                    $result = $this->managerAI->getResult(
                        self::AI_HASH,
                        array(
                            "messages" => array(
                                array(
                                    "role" => "system",
                                    "content" => "From the user's text write only the most important keywords separated"
                                        . " by the | character without spaces in between and a maximum total length of"
                                        . " 4000 characters. For example: keyword1|keyword2|keyword3"
                                ),
                                array(
                                    "role" => "user",
                                    "content" => $doc
                                )
                            )
                        )
                    );

                    if ($result[0]) {
                        $containsKeywords = $this->managerAI->getText($result[1], $result[2]);
                    }
                }
                set_sql_query(
                    $table,
                    array(
                        "information_value" => $doc,
                        "information_expiration" => get_future_date($row->information_duration),
                        "contains" => $containsKeywords !== null && strlen($containsKeywords) === 0 ? null : $containsKeywords
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