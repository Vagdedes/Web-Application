<?php

class LanguageTranslation
{
    private ?int $applicationID;

    public const USA_ENGLISH = 1;

    //todo introduce constants that are null application-id

    public function __construct($applicationID)
    {
        $this->applicationID = $applicationID;
    }

    public function getText($key, $replace = null): ?string
    {
        $value = null;

        if ($replace === null) {
            return $value;
        } else {
            $placeholder = new InformationPlaceholder();
            return $placeholder->replace($value, $replace);
        }
    }
}