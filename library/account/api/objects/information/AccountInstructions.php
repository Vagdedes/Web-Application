<?php

class AccountInstructions
{

    private Account $account;
    private array $localInstructions, $publicInstructions, $placeholders, $browse, $replacements;
    private string $placeholderStart, $placeholderMiddle, $placeholderEnd;

    public const
        DEFAULT_PLACEHOLDER_START = InformationPlaceholder::STARTER,
        DEFAULT_PLACEHOLDER_MIDDLE = InformationPlaceholder::DIVISOR_REPLACEMENT,
        DEFAULT_PLACEHOLDER_END = InformationPlaceholder::ENDER;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->placeholderStart = self::DEFAULT_PLACEHOLDER_START;
        $this->placeholderMiddle = self::DEFAULT_PLACEHOLDER_MIDDLE;
        $this->placeholderEnd = self::DEFAULT_PLACEHOLDER_END;
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
                            bool    $recursive = true): array
    {
        if ($object !== null && !empty($this->placeholders)) {
            $placeholders = $this->placeholders;

            foreach ($messages as $messageKey => $message) {
                if ($message === null) {
                    $messages[$messageKey] = "";
                }
            }

            foreach ($placeholders as $arrayKey => $placeholder) {
                if (isset($object->{$placeholder->placeholder})) {
                    unset($placeholders[$arrayKey]);
                    $value = $this->prepareValue(
                        $object->{$placeholder->placeholder},
                        $object,
                        $dynamicPlaceholders,
                        $recursive
                    );

                    foreach ($messages as $messageKey => $message) {
                        $messages[$messageKey] = str_replace(
                            $this->placeholderStart . $placeholder->placeholder . $this->placeholderEnd,
                            $value,
                            $message
                        );
                    }
                } else if ($placeholder->dynamic === null) {
                    unset($placeholders[$arrayKey]);
                }
            }

            foreach ($messages as $messageKey => $message) {
                $position = strpos($message, $this->placeholderStart);

                if ($position !== false) {
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
                                    case 2:
                                        try {
                                            $value = $this->prepareValue(
                                                call_user_func_array(
                                                    $keyWordMethod,
                                                    $explode
                                                ),
                                                $object,
                                                $dynamicPlaceholders,
                                                $recursive
                                            );
                                        } catch (Throwable $e) {
                                            $value = "";
                                        }
                                        break;
                                    case 3:
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
                                                $dynamicPlaceholders,
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
                                try {
                                    $value = $this->prepareValue(
                                        call_user_func_array(
                                            $keyWord,
                                            $explode
                                        ),
                                        $object,
                                        $dynamicPlaceholders,
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
                    }
                }
                $messages[$messageKey] = $message;
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
                        false
                    )[0];
                } else {
                    $value .= $row;
                }

                if ($arrayKey !== $size) {
                    $value .= "\n";
                }
            }
        }
        return $value;
    }

    private function prepareRow(object $row, string $data): string
    {
        if ($row->replace !== null && !empty($this->replacements)) {
            foreach ($this->replacements as $replace) {
                $data = str_replace(
                    $replace->find,
                    $replace->replacement,
                    $data
                );
            }
        }
        if ($row->browse !== null && !empty($this->browse)) {
            foreach ($this->browse as $browse) {
                if (str_contains($data, $browse->information_url)) {
                    $data .= ($browse->prefix ?? "")
                        . ($this->getURLData($row->information_url) ?? "")
                        . ($browse->suffix ?? "");
                }
            }
        }
        return $data;
    }

    private function getURLData($url): ?string
    {
        $data = get_domain_from_url($url) == "docs.google.com"
            ? get_raw_google_doc($url) :
            timed_file_get_contents($url);
        return is_string($data) ? $data : null;
    }

    // Separator

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getLocal(): array
    {
        if (!empty($this->localInstructions)) {
            $array = $this->localInstructions;

            foreach ($array as $value) {
                $value->information = $this->prepareRow($value, $value->information);
            }
            return $array;
        } else {
            return array();
        }
    }

    public function getPublic(): array
    {
        $cacheKey = array(__METHOD__, $this->account->getDetail("application_id"));
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $times = array();
            $array = $this->publicInstructions;

            if (!empty($array)) {
                foreach ($array as $arrayKey => $row) {
                    $timeKey = strtotime(get_future_date($row->information_duration));

                    if ($row->information_expiration !== null
                        && $row->information_expiration > get_current_date()) {
                        $times[$timeKey] = $row->information_duration;
                        $array[$arrayKey] = $row->information_value;
                    } else {
                        $doc = $this->getURLData($row->information_url);

                        if ($doc !== null) {
                            $doc = $this->prepareRow($row, $doc);
                            $times[$timeKey] = $row->information_duration;
                            $array[$arrayKey] = $doc;
                            set_sql_query(
                                InstructionsTable::PUBLIC,
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
                        } else {
                            if ($row->information_value !== null) {
                                $times[$timeKey] = $row->information_duration;
                                $array[$arrayKey] = $row->information_value;
                            } else {
                                unset($array[$arrayKey]);
                            }
                        }
                    }
                }

                if (!empty($times)) {
                    ksort($times);
                    set_key_value_pair($cacheKey, $array, array_shift($times));
                } else {
                    set_key_value_pair($cacheKey, $array, "1 minute");
                }
            }
            return $array;
        }
    }
}