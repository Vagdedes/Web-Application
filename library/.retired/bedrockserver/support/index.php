<html>
	<head>
		<title>
			<?php
			$urlSplit = explode(".", $_SERVER['SERVER_NAME']);
			$object = getObject($urlSplit, null);
			$title = htmlspecialchars($object->names["support"]);
			echo htmlspecialchars($object->name) . " | " . $title;
			?>
		</title>
		<link rel="shortcut icon" type="image/png" href="https://vagdedes.com/.images/bedrockIcon.png">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href='https://vagdedes.com/.css/universal.css?id="<?php echo rand(0, 2147483647)?>'>
		<link rel="stylesheet" href='https://vagdedes.com/.css/schemes.css?id="<?php echo rand(0, 2147483647)?>'>
		<script src="https://kit.fontawesome.com/44b5efe96d.js" crossorigin="anonymous"></script>
		<script src="https://www.google.com/recaptcha/api.js"></script>
		<?php
		loadTemplate(true);
		?>
	</head>
<body>
	<?php
			if ($object == null) {
				redirect_to_url($redirect_url);
				return;
			}
			if (!$object->is_patreon || $object->support_email == null) {
				return;
			}
			if (strlen($title) <= 1 && !is_alpha_numeric($title)) {
				redirect_to_url("../");
				return;
			}
			$urlSplitSize = sizeof($urlSplit);

			if ($urlSplitSize == 3) {
				$service_url_split = explode(".", $service_url);

				if ($service_url_split[0] != $urlSplit[$urlSplitSize - 2] // name
					|| $service_url_split[1] != $urlSplit[$urlSplitSize - 1]) { // domain
					redirect_to_url("http://" . $object->subdomain . "." . $service_url . "/support");
					return;
				}
			} else {
				redirect_to_url("http://" . $object->subdomain . "." . $service_url . "/support");
				return;
			}
			$email = get_form_post("email");
			$subject = get_form_post("subject");
			$info = get_form_post("info");

			if (strlen($email) > 0 || strlen($subject) > 0 || strlen($info) > 0) {
				if (!is_google_captcha_valid()) {
            	    echo "<div class='message'>Please complete the bot verification</div>";
				} else if (cooldownExists1("email")) {
					echo "<div class='message'>Please wait 5 minutes before contacting us again</div>";
				} else {
					$id = rand(0, 2147483647);
                    $title = "$object->name - $subject [ID: $id]";
                    $content = "ID: $id" . "\r\n"
                             . "Subject: $subject" . "\r\n"
                             . "Email: $email" . "\r\n"
                             . "" . "\r\n"
                             . $info;
					$receiver = $object->support_email;

					if ($receiver != null) {
						services_email(htmlspecialchars($receiver), $email, $title, $content);
						setCooldown1("email", 60 * 5);
					}
					echo "<div class='message'>Thanks for taking the time to contact us</div>";
				}
			}
		?>

	<?php include("../.tools/extensions/navigation.php"); ?>

	<div class="area">
			<div class="area_logo">
				<?php
				$image = htmlspecialchars($object->images["support"]);
				echo ($image != null && $image != "none" ? "<img src='data:image/png;base64,$image'>" : "<div class='customScheme'><i class='fa fa-envelope'></i></div>");
				?>
			</div>
            <div class="area_title">
                <?php echo htmlspecialchars($object->names["support"]); ?>
            </div>
            <div class="area_text">
                 <?php echo htmlspecialchars($object->descriptions["support"]); ?>
            </div>
            <div class="area_form">
                <form method="post">
                    <input type="text" name="email" placeholder="Email Address" minlength=5 maxlength=96>
                    <input type="text" name="subject" placeholder="Subject" minlength=2 maxlength=64>
                    <textarea name="info" placeholder="Information regarding contact..." minlength=24 maxlength=512 style="height: 150px; min-height: 150px; min-width: 100%; max-width: 100%;"></textarea>
                    <input type="submit" value="SUBMIT" class="button" id="blue">

                     <div class=recaptcha>
		                <div class=g-recaptcha data-sitekey=6Lf_zyQUAAAAAAxfpHY5Io2l23ay3lSWgRzi_l6B></div>
		            </div>
                </form>
            </div>
        </div>

	<?php include("../.tools/extensions/footer.php"); ?>
</body>
</html>
