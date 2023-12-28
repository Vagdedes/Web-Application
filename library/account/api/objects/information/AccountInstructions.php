<?php

class AccountInstructions
{

    private Account $account;
    private array $localInstructions, $publicInstructions, $placeholders;
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
                array("application_id", $this->account->getDetail("application_id")),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "plan_id ASC, priority DESC"
        );
        $this->publicInstructions = get_sql_query(
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
            "plan_id ASC, priority DESC"
        );
        $this->placeholders = get_sql_query(
            InstructionsTable::PLACEHOLDERS,
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
            foreach ($messages as $arrayKey => $message) {
                if ($message === null) {
                    $messages[$arrayKey] = "";
                }
            }
            $placeholders = $this->placeholders;
            $values = array();

            foreach ($placeholders as $arrayKey => $placeholder) {
                if (isset($object->{$placeholder->placeholder})) {
                    $values[] = array($placeholder, $object->{$placeholder->code_field} ?? "");
                    unset($placeholders[$arrayKey]);
                } else if ($placeholder->dynamic === null) {
                    $values[] = array($placeholder, "");
                    unset($placeholders[$arrayKey]);
                }
            }
            if (!empty($placeholders) && !empty($dynamicPlaceholders)) {
                foreach ($placeholders as $placeholder) {
                    if ($placeholder->dynamic !== null) {
                        $explode = explode($this->placeholderMiddle, $placeholder->placeholder);
                        $keyWord = array_shift($explode);

                        if (array_key_exists($keyWord, $dynamicPlaceholders)) {
                            $keyWordMethod = $dynamicPlaceholders[$keyWord];

                            if (is_array($keyWordMethod)) {
                                switch (sizeof($keyWordMethod)) {
                                    case 2:
                                        $value = call_user_func_array(
                                            $keyWordMethod,
                                            $explode
                                        );
                                        break;
                                    case 3:
                                        $parameters = array_pop($keyWordMethod);

                                        if (!empty($parameters)) {
                                            foreach ($parameters as $arrayKey => $parameter) {
                                                if (is_object($parameter)
                                                    && empty(get_object_vars($parameter))
                                                    && array_key_exists($arrayKey, $explode)) {
                                                    $parameters[$arrayKey] = $explode[$arrayKey];
                                                }
                                            }
                                        }
                                        $value = call_user_func_array(
                                            $keyWordMethod,
                                            $parameters
                                        );
                                        break;
                                    default:
                                        $value = "";
                                        break;
                                }
                            } else {
                                $value = call_user_func_array(
                                    $keyWord,
                                    $explode
                                );
                            }
                        } else {
                            $value = "";
                        }
                    } else {
                        $value = "";
                    }
                    $values[] = array($placeholder, $value);
                }
            }
            foreach ($values as $value) {
                $placeholder = $value[0];
                $value = $value[1];

                if (is_array($value)) {
                    $array = $value;
                    $size = sizeof($array);
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

                        if ($arrayKey !== ($size - 1)) {
                            $value .= "\n";
                        }
                    }
                }
                $object->placeholderArray[] = $value;

                if ($placeholder->include_previous !== null) {
                    $size = sizeof($object->placeholderArray);

                    for ($position = 1; $position <= min($placeholder->include_previous, $size); $position++) {
                        $positionValue = $object->placeholderArray[$size - $position];

                        foreach ($messages as $arrayKey => $message) {
                            if (!empty($message)) {
                                $messages[$arrayKey] = str_replace(
                                    $this->placeholderStart . $placeholder->placeholder . $this->placeholderEnd,
                                    $positionValue,
                                    $message
                                );
                            }
                        }
                    }
                }
                foreach ($messages as $arrayKey => $message) {
                    if (!empty($message)) {
                        $messages[$arrayKey] = str_replace(
                            $this->placeholderStart . $placeholder->placeholder . $this->placeholderEnd,
                            $value,
                            $message
                        );
                    }
                }
            }
        }
        return $messages;
    }

    // Separator

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getLocal(): array
    {
        return $this->localInstructions;
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
                        $doc = get_domain_from_url($row->information_url) == "docs.google.com"
                            ? get_raw_google_doc($row->information_url) :
                            timed_file_get_contents($row->information_url);

                        if ($doc !== null) {
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