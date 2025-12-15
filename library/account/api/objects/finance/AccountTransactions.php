<?php

class AccountTransactions
{
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    public function getSuccessful(mixed $types = null, int $limit = PaymentProcessor::limit): array
    {
        $array = array();

        foreach ($this->getTypes($types) as $transactionType) {
            $loopArray = array();

            switch ($transactionType) {
                case PaymentProcessor::PAYPAL:
                    $credential = $this->account->getAccounts()->hasAdded($transactionType);

                    if ($credential->isPositiveOutcome()) {
                        foreach ($credential->getObject() as $credential) {
                            foreach (find_paypal_transactions_by_data_pair(array("EMAIL" => abstract_search_sql_encode($credential)), $limit) as $transactionID => $transaction) {
                                $loopArray[$transactionID] = $transaction;
                            }
                        }
                    }
                    break;
                case PaymentProcessor::STRIPE:
                    $credential = $this->account->getAccounts()->hasAdded($transactionType);

                    if ($credential->isPositiveOutcome()) {
                        foreach ($credential->getObject() as $credential) {
                            foreach (find_stripe_transactions_by_data_pair(array("source.billing_details.email" => abstract_search_sql_encode($credential)), $limit) as $transactionID => $transaction) {
                                $loopArray[$transactionID] = $transaction;
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
            $array = array_merge($array, $loopArray);
        }
        return $array;
    }

    // Utilities

    private function getTypes(mixed $type): array
    {
        if ($type === null) {
            $type = PaymentProcessor::ALL_TYPES;
        } else if (!is_array($type)) {
            $type = array($type);
        }
        return $type;
    }
}
