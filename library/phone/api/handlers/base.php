<?php
use Twilio\Rest\Client;

function send_phone_message($phone, $message)
{
    $twilio_credentials = get_keys_from_file("/var/www/.structure/private/twilio_credentials", 4);

    if ($twilio_credentials === null) {
        return false;
    }
    try {
        $twilio = new Client($twilio_credentials[0], $twilio_credentials[1]);
        $message = $twilio->messages->create($phone,
            array(
                "from" => $twilio_credentials[3],
                "messagingServiceSid" => $twilio_credentials[2],
                "body" => $message
            )
        );
        return $message->status === "accepted" ? true : $message;
    } catch (Exception $exception) {
        return $exception;
    }
}