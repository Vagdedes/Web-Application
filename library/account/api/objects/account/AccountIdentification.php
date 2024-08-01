<?php

class AccountIdentification
{

    private Account $account;
    private string $identification;
    private const expiration_time = "6 hours";

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    private function run($repeat = true): void
    {
        global $account_identification_table;
        $accountID = $this->account->getDetail("id");
        set_sql_cache(self::class);
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
                        $this->account->clearMemory(self::class, function ($value) {
                            return is_array($value);
                        });
                        $this->identification = $code;
                        return;
                    }
                    break;
                } else if ($repeat) { // Try to retrieve if failed
                    $this->run(false);
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
                            $this->account->clearMemory(self::class, function ($value) {
                                return is_array($value);
                            });
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
        $this->run();
        return $this->identification;
    }

    private function create(): string
    {
        return strtoupper(random_string(7));
    }
}