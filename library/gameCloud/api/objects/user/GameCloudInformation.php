<?php

class GameCloudInformation
{
    private GameCloudUser $user;
    private ?Account $account;


    public function __construct($user)
    {
        $this->user = $user;
        $this->account = null;
    }

    public function guessPlatform($ipAddress): ?int
    {
        $license = $this->user->getLicense();

        if ($license !== null
            && $license > 0) {
            global $verifications_table;
            $query = get_sql_query(
                $verifications_table,
                array("platform_id"),
                array(
                    array("license_id", $license),
                    array("ip_address", $ipAddress),
                    array("platform_id", "IS NOT", null),
                    array("dismiss", null),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $platformID = $query[0]->platform_id;
                $this->user->setPlatform($platformID);
                return $platformID;
            }
        }
        return null;
    }

    public function getConnectionCount($productID, $ipAddress, $version): int
    {
        if ($this->user->isValid()) {
            global $connection_count_table; // Do not use cache, table is already memory-based
            set_sql_cache("1 minute");
            return sizeof(get_sql_query(
                $connection_count_table,
                array("id"),
                array(
                    array("platform_id", $this->user->getPlatform()),
                    array("license_id", $this->user->getLicense()),
                    array("product_id", $productID),
                    array("ip_address", $ipAddress),
                    array("version", $version)
                )
            ));
        } else {
            return 0;
        }
    }

    public function getAccount($checkDeletion = true): Account
    {
        if ($this->account === null) {
            $application = new Application(null);

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
                        $this->account = $application->getAccount($query[0]->account_id, null, null, null, $checkDeletion);
                    } else {
                        $this->account = $application->getAccount(0);
                    }
                } else {
                    $this->account = $application->getAccount(0);
                }
            } else {
                $this->account = $application->getAccount(0);
            }
        }
        return $this->account;
    }

    public function ownsProduct($productID): bool
    {
        $account = $this->getAccount();
        return $account->exists()
            && $account->getPurchases()->owns($productID)->isPositiveOutcome();
    }
}
