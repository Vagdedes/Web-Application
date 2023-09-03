<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Vagdedes Services | High-Quality Minecraft Plugins</title>
    <meta name="description" content="Vagdedes Services powers dozens of Minecraft servers with high-quality plugins, with our most credible project being the Spartan AntiCheat.">
    <link rel="shortcut icon" type="image/png" href="../../../vagdedes/.images/icon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href='https://vagdedes.com/.css/universal.css?id=<?php echo rand(0, 2147483647) ?>'>
    <script src="https://www.google.com/recaptcha/api.js"></script>
</head>

<body>

<?php
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/form.php';

require_once '/var/www/.structure/library/email/init.php';
$message = get_form_get("message");

if (strlen($message) > 0) {
    $message = htmlspecialchars(substr($message, 0, 256), ENT_QUOTES, 'UTF-8');
    echo "<div class='message'>$message</div>";
}
?>

<h1>
    <div class="intro">
        <div class='intro_image'>
            <img src='https://vagdedes.com/.images/services.png' alt='company logo'>
        </div>
    </div>

    <div class="area">
        <div class="area_logo">
            <div class='search'>
                <ul>
                    <li class='search_top'></li>
                    <li class='search_bottom'></li>
                </ul>
            </div>
        </div>
        <div class="area_text">
            <b>Vagdedes Services</b> powers dozens of Minecraft servers with high-quality plugins, with our most credible project being the <a href="https://vagdedes.com/spartan">Spartan AntiCheat</a>, an advanced Minecraft anti-cheat meant to protect players from hack modules. Scroll down to connect with us.
        </div>
        <p>
        <div class="area_form" id="marginless">
            <a href="https://vagdedes.com/account" class="button" id="red" style="width: 400px;">Go To Store</a>
        </div>
        <div class="area_text">
            <p>
                <iframe src="https://discordapp.com/widget?id=289384242075533313&theme=dark" width="350" height="500"
                        allowtransparency="false" frameborder="0"></iframe>
        </div>
    </div>
</h1>

<h2>
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
            Contact Form
        </div>
        <div class="area_form">
            <a href="https://vagdedes.com/contact/" class="button" id="green">CONTACT US</a>
        </div>
    </div>
</h2>

<h3>
    <?php include("/var/www/.structure/design/footer/footer.php"); ?>
</h3>
</body>

</html>
