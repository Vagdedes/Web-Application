<?php

class GameCloudInformation
{
    private GameCloudUser $user;
    private ?Account $account;


    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
        $this->account = null;
    }

    public function getAccount(bool $checkDeletion = true): Account
    {
        if ($this->account === null) {
            $account = new Account();

            if ($this->user->isValid()) {
                $query = get_accepted_platforms(array("accepted_account_id"), $this->user->getPlatform());

                if (!empty($query)) {
                    global $added_accounts_table;
                    set_sql_cache("1 minute");
                    $query = get_sql_query(
                        $added_accounts_table,
                        array("account_id"),
                        array(
                            array("accepted_account_id", $query[0]->accepted_account_id),
                            array("credential", $this->user->getLicense()),
                            array("deletion_date", null)
                        ),
                        null,
                        1
                    );

                    if (!empty($query)) {
                        $this->account = $account->getNew($query[0]->account_id, null, null, null, $checkDeletion);
                    } else {
                        $this->account = $account;
                    }
                } else {
                    $this->account = $account;
                }
            } else {
                $this->account = $account;
            }
        }
        return $this->account;
    }

    public function ownsProduct(int|string $productID): bool
    {
        $account = $this->getAccount();
        return $account->exists()
            && $account->getPurchases()->owns($productID)->isPositiveOutcome();
    }
}
