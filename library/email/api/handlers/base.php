<?php

function personal_self_email($from, $subject, $content)
{
    $email_credentials = get_keys_from_file("/var/www/.structure/private/email_credentials", 6);

    if ($email_credentials === null) {
        return false;
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $email_credentials[0];
    $mail->Password = $email_credentials[1];
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    $mail->addReplyTo($from, null);
    $mail->From = $from;
    $mail->FromName = "(Automated Email)";

    $mail->addAddress($email_credentials[0], "Vagdedes");

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = nl2br($content);
    $mail->AltBody = nl2br($content);

    if (!$mail->send()) {
        return $mail->ErrorInfo;
    }
    return true;
}

function services_self_email($from, $subject, $content)
{
    $email_credentials = get_keys_from_file("/var/www/.structure/private/email_credentials", 6);

    if ($email_credentials === null) {
        return false;
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $email_credentials[4];
    $mail->Password = $email_credentials[5];
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    $mail->addReplyTo($from, null);
    $mail->From = $from;
    $mail->FromName = "(Automated Email)";

    $mail->addAddress($email_credentials[4], null);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = nl2br($content);
    $mail->AltBody = nl2br($content);

    if (!$mail->send()) {
        return $mail->ErrorInfo;
    }
    return true;
}

function services_email($to, $from, $subject, $content)
{
    $email_credentials = get_keys_from_file("/var/www/.structure/private/email_credentials", 6);

    if ($email_credentials === null) {
        return false;
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $email_credentials[4];
    $mail->Password = $email_credentials[5];
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    if ($from == null) {
        $from = $email_credentials[2];
    }

    $mail->addReplyTo($from, null);
    $mail->From = $from;
    $mail->FromName = "(Automated Email)";

    $recipients = explode(",", $to);

    foreach ($recipients as $recipient) {
        if (strlen($recipient) >= 5 && strpos($recipient, "@") !== false && strpos($recipient, ".") !== false) {
            $mail->addAddress($recipient, null);
        } else {
            throw new Exception("Incorrect email '$recipient'");
        }
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = nl2br($content);
    $mail->AltBody = nl2br($content);

    if (!$mail->send()) {
        return $mail->ErrorInfo;
    }
    return true;
}