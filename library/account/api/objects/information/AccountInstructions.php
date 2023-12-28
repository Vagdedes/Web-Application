<?php

class AccountInstructions
{

    private Account $account;
    private array $localInstructions, $publicInstructions, $placeholders;
    private string $placeholderStart, $placeholderMiddle, $placeholderEnd;

    public const
        DEFAULT_PLACEHOLDER_START = "%%__",
        DEFAULT_PLACEHOLDER_MIDDLE = "__",
        DEFAULT_PLACEHOLDER_END = "__%%";

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->placeholderStart = self::DEFAULT_PLACEHOLDER_START;
        $this->placeholderMiddle = self::DEFAULT_PLACEHOLDER_MIDDLE;
        $this->placeholderEnd = self::DEFAULT_PLACEHOLDER_END;
        $this->localInstructions = get_sql_query(
            InstructionsTable::BOT_LOCAL_INSTRUCTIONS,
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
            InstructionsTable::BOT_PUBLIC_INSTRUCTIONS,
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
            InstructionsTable::BOT_INSTRUCTION_PLACEHOLDERS,
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

    public function replace(array  $messages, ?object $object,
                            string $placeholderStart = self::DEFAULT_PLACEHOLDER_START,
                            string $placeholderMiddle = self::DEFAULT_PLACEHOLDER_MIDDLE,
                            string $placeholderEnd = self::DEFAULT_PLACEHOLDER_END,
                            bool   $recursive = true): array
    {
        if ($object !== null && !empty($this->placeholders)) {
            $replaceFurther = false;

            foreach ($messages as $arrayKey => $message) {
                if ($message === null) {
                    $messages[$arrayKey] = "";
                }
            }
            foreach ($this->placeholders as $placeholder) {
                if (isset($object->{$placeholder->placeholder})) {
                    $value = $object->{$placeholder->code_field};
                } else if ($placeholder->dynamic !== null) {
                    $keyWord = explode($placeholderMiddle, $placeholder->placeholder, 3);
                    $limit = sizeof($keyWord) === 2 ? $keyWord[1] : 0;

                    switch ($keyWord[0]) {
                        case "publicInstructions":
                            $value = $this->getPublic();
                            $replaceFurther = $recursive;
                            break;
                        case "botReplies":
                            $value = $this->plan->conversation->getReplies($object->userID, $limit, false);
                            break;
                        case "botMessages":
                            $value = $this->plan->conversation->getMessages($object->userID, $limit, false);
                            break;
                        case "allMessages":
                            $value = $this->plan->conversation->getConversation($object->userID, $limit, false);
                            break;
                        default:
                            $value = "";
                            break;
                    }
                } else {
                    $value = "";
                }

                if (is_array($value)) {
                    $array = $value;
                    $size = sizeof($array);
                    $value = "";

                    foreach ($array as $arrayKey => $row) {
                        if ($replaceFurther) {
                            $value .= $this->replace(
                                array($row),
                                $object,
                                $placeholderStart,
                                $placeholderMiddle,
                                $placeholderEnd,
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
                                    $placeholderStart . $placeholder->placeholder . $placeholderEnd,
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
                            $placeholderStart . $placeholder->placeholder . $placeholderEnd,
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

    public function getLocal(): array
    {
        return $this->localInstructions;
    }

    public function getPublic(): array
    {
        $cacheKey = array(__METHOD__, $this->plan->applicationID, $this->plan->planID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $times = array();
            $array = $this->publicInstructions;

            if (!empty($array)) {
                global $logger;

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
                                InstructionsTable::BOT_PUBLIC_INSTRUCTIONS,
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
                            $logger->logError($this->plan->planID, "Failed to retrieve value for: " . $row->information_url);

                            if ($row->information_value !== null) {
                                $times[$timeKey] = $row->information_duration;
                                $array[$arrayKey] = $row->information_value;
                                $logger->logError($this->plan->planID, "Used backup value for: " . $row->information_url);
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