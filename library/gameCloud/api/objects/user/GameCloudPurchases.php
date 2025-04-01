<?php

class GameCloudPurchases
{
    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function getFromDatabase(
        string $email,
        string $dataDirectory
    ): ?bool
    {
        $query = get_sql_query(
            GameCloudVariables::PURCHASES_TABLE,
            array(
                "true_false"
            ),
            array(
                array("email_address", $email),
                array("data_directory", $dataDirectory),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (empty($query)) {
            return null;
        } else {
            return $query[0]->true_false !== null;
        }
    }

    public function hasPayPalTransaction(
        string                 $email,
        int|float|string|array $amount,
        int                    $limit = 1,
        ?string                $reason = null,
        ?string                $creationDate = null
    ): bool
    {
        $arguments = array(
            "EMAIL" => $email
        );

        if ($reason !== null) {
            $arguments["L_NAME0"] = $reason;
        }
        if (is_array($amount)) {
            foreach ($amount as $single) {
                $search = $arguments;
                $search["AMT"] = $single;
                $search = find_paypal_transactions_by_data_pair(
                    $search,
                    $limit,
                    $creationDate
                );

                if (!empty($search)) {
                    return true;
                }
            }
        } else {
            $arguments["AMT"] = $amount;

            if (!empty(find_paypal_transactions_by_data_pair(
                $arguments,
                $limit,
                $creationDate
            ))) {
                return true;
            }
        }
        return false;
    }

    public function hasLegacyPayPalTransaction(string $email): bool
    {
        return self::hasPayPalTransaction(
            $email,
            array(
                9.99, // Spartan One, Vacan One, Combat Detection, Movement Detections, World Detections
                29.99, // Spartan Syn
                37.49, // Spartan Syn
                "37.50", // Spartan Syn
                16.97, // Spartan Syn
                12.49, // Spartan Syn
                17.89, // Spartan Syn
                15.99, // Spartan Syn
                18.79, // Spartan Syn, Ultimate Stats, Global Bans, Anti Alt Account, File GUI, Auto Sync
                50, // Spartan Syn
                "9.00", // Unlimited Detection Slots (6 Months),
                "13.30", // Unlimited Detection Slots (4 Months),
                6.83, // Unlimited Detection Slots (8 Months)
            )
        );
    }

}
