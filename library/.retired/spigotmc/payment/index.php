<!DOCTYPE html>
<html lang="en">
<?php
$websiteTitle = "Vagdedes Services";
?>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?php echo $websiteTitle ?></title>
    <meta name="description"
          content="Vagdedes Services powers dozens of Minecraft servers with high-quality plugins. Complete this form to communicate with us.">
    <link rel="shortcut icon" type="image/png" href="https://vagdedes.com/.images/icon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href='https://vagdedes.com/.css/universal.css?id=<?php echo rand(0, 2147483647) ?>'>
    <script src="https://www.google.com/recaptcha/api.js"></script>
</head>

<body>

<?php
$username = get_form_post("username");
$email = get_form_post("email");
$info = get_form_post("info");

$usernameString = strlen($username);
$emailString = strlen($email);
$infoString = strlen($info);

if ($usernameString >= 3 && $usernameString <= 32
    && $emailString >= 6 && $emailString <= 384
    && $infoString >= 8 && $infoString <= 256) {

    $cacheKey = array(
        get_client_ip_address(),
        "spigotmc-payment-form"
    );

    if (!is_google_captcha_valid()) {
        echo "<div class='message'>Please complete the bot verification</div>";
    } else if (has_memory_cooldown($cacheKey, null, false)) {
        echo "<div class='message'>Please wait a few minutes before contacting us again</div>";
    } else {
        $session = getAccountSession1();
        $id = rand(0, 2147483647);
        $username = strip_tags($username);
        $email = strip_tags($email);
        $title = "Vagdedes.com - SpigotMC Payment Form [ID: $id]";
        /*$content = "ID: $id" . "\r\n"
            . "Username: $username" . "\r\n"
            . "Email: $email" . "\r\n"
            . "\r\n"
            . "Payment Methods:\r\n"
            . strip_tags($info)
            . "\r\n"
            . "\r\n"
            . "<b>Hello " . $username . ", please check if our <a href='https://minecraft.store.vagdedes.com/'>Minecraft Store</a> is suitable for you, otherwise reply to this email.</b>";*/
        $content = "ID: $id" . "\r\n"
            . "Username: $username" . "\r\n"
            . "Email: $email" . "\r\n"
            . "\r\n"
            . "Payment Methods:\r\n"
            . strip_tags($info)
            . "\r\n"
            . "\r\n"
            . "<b>We will reply at first chance</b>";

        if (services_email($email, null, $title, $content)) {
            has_memory_cooldown($cacheKey, "5 minutes");
            echo "<div class='message'>Thanks for taking the time to contact us</div>";
        } else {
            echo "<div class='message'>An error occurred, please contact us at: contact@vagdedes.com</div>";
        }
    }
}
?>

<div class="area" id="darker">
    <div class="area_logo">
        <div class="paper">
            <ul>
                <li class="paper_top"></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
            </ul>
        </div>
    </div>
    <div class="area_title">
        SpigotMC Payment Form
    </div>
    <div class="area_text">
        Contact us by using the form below.
    </div>
    <div class="area_form">
        <form method="post">
            <input type="text" name="username" placeholder="SpigotMC Username" minlength=3 maxlength=32>
            <input type="text" name="email" placeholder="Email Address" minlength=5 maxlength=384>
            <textarea name="info" placeholder="Please use this box to write all the payment methods you can use to see if we can assist you" minlength=8 maxlength=256
                      style="height: 150px; min-height: 150px;"></textarea>
            <input type="submit" value="CONTACT US" class="button" id="green">

            <div class=recaptcha>
                <div class=g-recaptcha data-sitekey=6Lf_zyQUAAAAAAxfpHY5Io2l23ay3lSWgRzi_l6B></div>
            </div>
        </form>
    </div>
</div>

<?php include("/var/www/.structure/library/design/accountOld/footer/footer.php"); ?>
</body>

</html>