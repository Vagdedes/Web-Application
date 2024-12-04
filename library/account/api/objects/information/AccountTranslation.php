<?php

class AccountTranslation
{

    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function translate(
        string  $language,
        string  $text,
        ?string $expiration = null,
        bool    $details = false,
        bool    $force = false): MethodReply
    {
        $hash = array_to_integer(array($language, $text), true);
        $date = get_current_date();
        $query = get_sql_query(
            AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
            $details ? null : array("after"),
            array(
                array("hash", $hash),
                array("language", $language),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if ($force || empty($query)) {
            //AIModelFamily::CHAT_GPT_PRO
            return new MethodReply(false);
        } else {
            $query = $query[0];
            return new MethodReply(
                true,
                null,
                $details ? $query : $query->after
            );
        }
    }

}
