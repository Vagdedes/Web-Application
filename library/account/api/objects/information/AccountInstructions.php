<?php

class AccountInstructions
{

    private const AI_HASH = 596802337;

    private Account $account;
    private array
        $localInstructions,
        $publicInstructions,
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
                            ?array  $callables): array
    {
        if ($object !== null) {
            foreach ($object as $objectKey => $objectValue) {
                foreach ($messages as $messageKey => $message) {
                    $messages[$messageKey] = str_replace(
                        $this->placeholderStart . $objectKey . $this->placeholderEnd,
                        is_array($objectValue)
                            ? implode("\n", $objectValue)
                            : (is_object($objectValue) ? json_encode($objectValue) :
                            ($objectValue === null ? "" : $objectValue)),
                        $message
                    );
                }
            }
        }

        if (!empty($callables)) {
            foreach ($callables as $callableKey => $callable) {
                $callable = $callable();

                foreach ($messages as $messageKey => $message) {
                    $messages[$messageKey] = str_replace(
                        $this->placeholderStart . $callableKey . $this->placeholderEnd,
                        is_array($callable) ? implode("\n", $callable) : $callable,
                        $message
                    );
                }
            }
        }
        if (!empty($this->extra)) {
            foreach ($this->extra as $extra) {
                $messages[] = $extra;
            }
        }
        return $messages;
    }

    private function getURLData(object $row): ?string
    {
        if ($row->information_expiration !== null
            && $row->information_expiration > get_current_date()) {
            return $row->information_value;
        } else {
            $doc = get_raw_google_doc($row->information_url);

            if ($doc === null) {
                $doc = timed_file_get_contents($row->information_url);
            }

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
                        $containsKeywords = $this->managerAI->getText($result[1], $result[2]);
                    }
                }
                set_sql_query(
                    InstructionsTable::PUBLIC,
                    array(
                        "information_value" => $row->information_url . "|" . $doc,
                        "information_expiration" => get_future_date($row->information_duration),
                        "contains" => $containsKeywords !== null && strlen($containsKeywords) === 0 ? null : strtolower($containsKeywords)
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
                        $array[$arrayKey] = $row->information;
                    } else {
                        unset($array[$arrayKey]);
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
                    if ($userInput === null || empty($array->contains)) {
                        $doc = $this->getURLData($row);
                    } else {
                        $doc = null;

                        foreach ($array->contains as $contains) {
                            if (str_contains($userInput, $contains)) {
                                $doc = $this->getURLData($row);
                                break;
                            }
                        }
                    }

                    if ($doc !== null) {
                        if (false && $row->sub_directories !== null) {
                            $links = get_urls_from_string($url);

                            if (!empty($links)) {
                                $domain = get_domain_from_url($row->information_url, true);

                                foreach ($links as $link) {
                                    if ($link != $row->information_url
                                        && $link != ($row->information_url . "/")
                                        && ($row->sub_directories == 2
                                            || get_domain_from_url($link, true) == $domain)) {
                                        $url = $this->getRawURLData($link);

                                        if ($url !== null) {
                                            $doc .= ($row->prefix ?? "")
                                                . "Start of '" . $link . "':\n"
                                                . json_encode($url)
                                                . "\nEnd of '" . $link . "'"
                                                . ($row->suffix ?? "");
                                        }
                                    }
                                }
                            }
                        }
                        $array[$arrayKey] = $doc;
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