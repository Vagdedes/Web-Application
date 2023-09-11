<?php

class AccountPhoneNumber
{
    private Account $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    public function send($case, $detailsArray = null, $type = "account"): bool
    {
        $phoneNumber = $this->account->getAccounts()->getAdded(AccountAccounts::PHONE_NUMBER, 1);

        if (!empty($phoneNumber)) {
            $applicationID = $this->account->getDetail("application_id");

            if ($applicationID === null) {
                $applicationID = 0;
            }
            $phoneNumber = prepare_phone_number($phoneNumber[0]);
            return $this->account->getSettings()->isEnabled(
                    "receive_" . $type . "_phone_messages",
                    $type === "account"
                )
                && send_phone_message_by_plan(
                    $applicationID . "-" . $case,
                    $phoneNumber,
                    $detailsArray,
                ) === 1;
        }
        return false;
    }
}