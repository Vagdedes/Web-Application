<?php
if (false) {
    ini_set('include_path', '/usr/share/php');
    "twilio-php-master/Twilio/autoload.php";

    //use Twilio\Rest\Client;
    $number = get_form_get("number");
    $country = get_form_get("country");
    $message = get_form_get("message");

    if (strlen($number) >= 8 && strlen($country) > 0 && strlen($message) >= 3
        && $_SERVER['HTTP_USER_AGENT'] == "sms4s3rv1c3s.C0M") {
        $account_sid = "ACbea26219ec7b56f03be0216b25122704";
        $auth_token = "07189285585dea9d8472ea23d13b0a42";
        $twilio_number = "+17405954103";

        $client = new Client($account_sid, $auth_token);
        $client->messages->create(
            "+" . $country . $number,
            array(
                'from' => $twilio_number,
                'body' => $message
            )
        );
        echo "true";
    }
}
