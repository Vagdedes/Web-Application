<?php

class GameCloudPurchases
{
    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function addToDatabase(
        string  $email,
        string  $dataDirectory,
        bool    $trueFalse,
        ?string $expirationTime = null,
        ?string $justification = null
    ): bool
    {
        if (sql_insert(
            GameCloudVariables::PURCHASES_TABLE,
            array(
                "platform_id" => $this->user->getPlatform(),
                "license_id" => $this->user->getLicense(),
                "email_address" => $email,
                "data_directory" => $dataDirectory,
                "true_false" => $trueFalse,
                "creation_date" => get_current_date(),
                "creation_reason" => $justification,
                "expiration_date" => $expirationTime !== null ? get_future_date($expirationTime) : null
            )
        )) {
            return true;
        } else {
            return false;
        }
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
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null,
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return null;
        } else {
            return $query[0]->true_false !== null;
        }
    }

    public function hasPayPalTransaction(
        ?string                     $email,
        int|float|string|array|null $amount,
        int                         $limit = 1,
        int|float|string|array|null $reason = null,
        ?string                     $custom = null,
        ?string                     $creationDate = null
    ): ?array
    {
        $arguments = array();

        if ($email !== null) {
            $arguments["EMAIL"] = $email . "\"";
        }
        if ($custom !== null) {
            $arguments["CUSTOM"] = $custom;
        }
        if (is_array($amount)) {
            if (is_array($reason)) {
                foreach ($amount as $amountSingle) {
                    foreach ($reason as $reasonSingle) {
                        $search = $arguments;
                        $search["AMT"] = $amountSingle . "\"";
                        $search["L_NAME0"] = $reasonSingle;
                        $search = find_paypal_transactions_by_data_pair(
                            $search,
                            $limit,
                            $creationDate
                        );

                        if (!empty($search)) {
                            return $search;
                        }
                    }
                }
            } else {
                if ($reason !== null) {
                    $arguments["L_NAME0"] = $reason;
                }
                foreach ($amount as $single) {
                    $search = $arguments;
                    $search["AMT"] = $single . "\"";
                    $search = find_paypal_transactions_by_data_pair(
                        $search,
                        $limit,
                        $creationDate
                    );

                    if (!empty($search)) {
                        return $search;
                    }
                }
            }
        } else if (is_array($reason)) {
            if ($amount !== null) {
                $arguments["AMT"] = $amount . "\"";
            }
            foreach ($reason as $single) {
                $search = $arguments;
                $search["L_NAME0"] = $single;
                $search = find_paypal_transactions_by_data_pair(
                    $search,
                    $limit,
                    $creationDate
                );

                if (!empty($search)) {
                    return $search;
                }
            }
        } else {
            if ($amount !== null) {
                $arguments["AMT"] = $amount . "\"";
            }
            if ($reason !== null) {
                $arguments["L_NAME0"] = $reason;
            }
            $search = find_paypal_transactions_by_data_pair(
                $arguments,
                $limit,
                $creationDate
            );

            if (!empty($search)) {
                return $search;
            }
        }
        return null;
    }

    public function hasStripeTransaction(
        ?string                     $email,
        int|float|string|array|null $amount,
        int                         $limit = 1,
        int|float|string|array|null $reason = null,
        ?string                     $creationDate = null
    ): ?array
    {
        $arguments = array();

        if ($email !== null) {
            $arguments["source.billing_details.email"] = $email . "\"";
        }
        if (is_array($amount)) {
            if (is_array($reason)) {
                foreach ($amount as $amountSingle) {
                    foreach ($reason as $reasonSingle) {
                        $search = $arguments;
                        $search["amount"] = $amountSingle . "\"";
                        $search["description"] = $reasonSingle;
                        $search = find_stripe_transactions_by_data_pair(
                            $search,
                            $limit,
                            $creationDate
                        );

                        if (!empty($search)) {
                            return $search;
                        }
                    }
                }
            } else {
                if ($reason !== null) {
                    $arguments["description"] = $reason;
                }
                foreach ($amount as $single) {
                    $search = $arguments;
                    $search["amount"] = $single . "\"";
                    $search = find_stripe_transactions_by_data_pair(
                        $search,
                        $limit,
                        $creationDate
                    );

                    if (!empty($search)) {
                        return $search;
                    }
                }
            }
        } else if (is_array($reason)) {
            if ($amount !== null) {
                $arguments["amount"] = $amount . "\"";
            }
            foreach ($reason as $single) {
                $search = $arguments;
                $search["description"] = $single;
                $search = find_stripe_transactions_by_data_pair(
                    $search,
                    $limit,
                    $creationDate
                );

                if (!empty($search)) {
                    return $search;
                }
            }
        } else {
            if ($amount !== null) {
                $arguments["amount"] = $amount . "\"";
            }
            if ($reason !== null) {
                $arguments["description"] = $reason;
            }
            $search = find_stripe_transactions_by_data_pair(
                $arguments,
                $limit,
                $creationDate
            );

            if (!empty($search)) {
                return $search;
            }
        }
        return null;
    }

    public function hasSpartanPayPalTransaction(string $email): bool
    {
        return !empty(self::hasPayPalTransaction(
            $email,
            array(
                19.99,
                22.49,
                "22.50",
                "18.79",
                14.99,
                "15.00",
                "8",
                "7.50",
                "9.90",
                "10.00",
                7.99,
                "13.20",
                "16.00",
                16.49,
                "13.00",
                17.89,
                "13.50",
                "18.00",
                "20.00",
                "22.00",
                "25.00",
                19.97,
                18.99,
                11.49,
                24.99
            ),
            1,
            array(
                "Spartan",
                "Vacan"
            ),
            null,
            get_past_date("1 year")
        ));
    }

    public function hasSpartanExtendedPayPalTransaction(string $email): bool
    {
        return !empty(self::hasPayPalTransaction(
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
        ));
    }

}
