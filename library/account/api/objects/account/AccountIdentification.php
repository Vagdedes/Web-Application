<?php

class AccountIdentification
{

    private string $identification;
    private const expiration_time = "1 hour";

    public function __construct(Account $account)
    {
        global $account_identification_table;
        $accountID = $account->getDetail("id");
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            $account_identification_table,
            array("id", "code", "expiration_date"),
            array(
                array("account_id", $accountID),
            ),
            null,
            1
        );

        if (empty($query)) {
            while (true) {
                $code = $this->create();

                if (empty(get_sql_query(
                    $account_identification_table,
                    array("id"),
                    array(
                        array("code", $code),
                    ),
                    null,
                    1
                ))) {
                    if (sql_insert(
                        $account_identification_table,
                        array(
                            "account_id" => $accountID,
                            "code" => $code,
                            "expiration_date" => get_future_date(self::expiration_time)
                        )
                    )) {
                        clear_memory(array(self::class), true);
                        $this->identification = $code;
                        return;
                    }
                    break;
                }
            }
        } else {
            $query = $query[0];

            if ($query->expiration_date > get_current_date()) {
                $this->identification = $query->code;
                return;
            } else {
                while (true) {
                    $code = $this->create();

                    if (empty(get_sql_query(
                        $account_identification_table,
                        array("id"),
                        array(
                            array("code", $code),
                        ),
                        null,
                        1
                    ))) {
                        if (set_sql_query(
                            $account_identification_table,
                            array(
                                "code" => $code,
                                "expiration_date" => get_future_date(self::expiration_time)
                            ),
                            array(
                                array("id", $query->id),
                            ),
                            null,
                            1
                        )) {
                            clear_memory(array(self::class), true);
                            $this->identification = $code;
                            return;
                        }
                        break;
                    }
                }
            }
        }
        $this->identification = $accountID;
    }

    public function get(): string
    {
        return $this->identification;
    }

    private function create(): string
    {
        return strtoupper(random_string(6));
    }
}