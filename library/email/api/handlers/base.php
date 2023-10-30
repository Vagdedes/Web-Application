<?php
$email_credentials_directory = "/var/www/.structure/private/email_credentials";

class EmailBase
{
    public const
        email_credential_lines = 10,
        VAGDEDES_CONTACT = 2,
        VAGDEDES_NO_REPLY = 4,
        IDEALISTIC_CONTACT = 6,
        IDEALISTIC_NO_REPLY = 8;
}

function personal_self_email($from, $subject, $content): bool|string
{
    global $email_credentials_directory;
    $email_credentials = get_keys_from_file($email_credentials_directory, EmailBase::email_credential_lines);

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

function services_self_email($from, $subject, $content, $startingLinePosition = EmailBase::VAGDEDES_CONTACT): bool|string
{
    global $email_credentials_directory;
    $email_credentials = get_keys_from_file($email_credentials_directory, EmailBase::email_credential_lines);

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

function services_email($to, $from, $subject, $content, $startingLinePosition = EmailBase::VAGDEDES_NO_REPLY): bool|string
{
    global $email_credentials_directory;
    $email_credentials = get_keys_from_file($email_credentials_directory, EmailBase::email_credential_lines);

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