<?php
require '/var/www/.structure/library/base/utilities.php';
require '/var/www/.structure/library/base/form.php';
require '/var/www/.structure/library/paypal/init.php';
$amount = get_form_get("amount");

if (is_numeric($amount) && $amount > 0.0) {
    redirect_to_url($paypal_me_url . $paypal_me_name_business . "/" . $amount);
} else {
    redirect_to_url($paypal_me_url . $paypal_me_name_business);
}
exit();
