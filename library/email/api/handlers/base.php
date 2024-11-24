<?php

function personal_self_email(string $from, int|string|float $subject, int|string|float $content): bool|string
{
    $email_credentials = get_keys_from_file(EmailVariables::CREDENTIALS_DIRECTORY, EmailVariables::email_credential_lines);

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

function services_self_email(string $from, int|string|float $subject, int|string|float $content,
                             int    $startingLinePosition = EmailVariables::IDEALISTIC_CONTACT): bool|string
{
    $email_credentials = get_keys_from_file(EmailVariables::CREDENTIALS_DIRECTORY, EmailVariables::email_credential_lines);

    if ($email_credentials === null) {
        return false;
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $email_credentials[$startingLinePosition];
    $mail->Password = $email_credentials[$startingLinePosition + 1];
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    $mail->addReplyTo($from, null);
    $mail->From = $from;
    $mail->FromName = "(Automated Email)";

    $mail->addAddress($email_credentials[$startingLinePosition], null);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = nl2br($content);
    $mail->AltBody = nl2br($content);

    if (!$mail->send()) {
        return $mail->ErrorInfo;
    }
    return true;
}

function services_email(string           $to, ?string $from,
                        int|string|float $subject, int|string|float $content,
                        int              $startingLinePosition = EmailVariables::IDEALISTIC_NO_REPLY): bool|string
{
    $email_credentials = get_keys_from_file(EmailVariables::CREDENTIALS_DIRECTORY, EmailVariables::email_credential_lines);

    if ($email_credentials === null) {
        return false;
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer();

    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $email_credentials[$startingLinePosition];
    $mail->Password = $email_credentials[$startingLinePosition + 1];
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    if ($from === null) {
        $from = $email_credentials[$startingLinePosition];
    }

    $mail->addReplyTo($from, null);
    $mail->From = $from;
    $mail->FromName = "(Automated Email)";

    $recipients = explode(",", $to);

    foreach ($recipients as $recipient) {
        if (strlen($recipient) >= 5 && str_contains($recipient, "@") && str_contains($recipient, ".")) {
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