<?php
require '/var/www/.structure/library/base/form.php';
$id = get_form_get("id");

if (!empty($id)) {
    require '/var/www/.structure/library/base/communication.php';

    if (is_private_connection()) {
        require '/var/www/.structure/library/paypal/init.php';

        // Separator
        for ($i = 0; $i < 3; $i++) {
            switch ($i) {
                case 0:
                    access_business_paypal_account();
                    break;
                case 1:
                    access_personal_paypal_account();
                    break;
                case 2:
                    access_deactivated_personal_paypal_account();
                    break;
                default:
                    exit_paypal_account();
                    break;
            }
            $object = new stdClass();
            $transaction = get_paypal_transaction_details($id);

            if (is_array($transaction) && isset($transaction["ACK"]) && $transaction["ACK"] === "Success") {
                foreach ($transaction as $key => $value) {
                    $object->{$key} = $value;
                }
                break;
            }
        }

        header('Content-type: Application/JSON');
        echo json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        require '/var/www/.structure/library/base/redirect.php';
    }
} else {
    require '/var/www/.structure/library/base/redirect.php';
}