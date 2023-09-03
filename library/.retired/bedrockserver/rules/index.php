<html>
	<head>
		<title>
			<?php
			require_once '../.tools/api/object.php';
			$object = getObject(explode(".", $_SERVER['SERVER_NAME']), null);
			$title = htmlspecialchars($object->names["rules"]);
			echo htmlspecialchars($object->name) . " | " . $title;
			?>
		</title>
		<link rel="shortcut icon" type="image/png" href="https://vagdedes.com/.images/bedrockIcon.png">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href='https://vagdedes.com/.css/universal.css?id="<?php echo rand(0, 2147483647)?>'>
		<link rel="stylesheet" href='https://vagdedes.com/.css/schemes.css?id="<?php echo rand(0, 2147483647)?>'>
		<script src="https://kit.fontawesome.com/44b5efe96d.js" crossorigin="anonymous"></script>
		<?php
		require_once '../.tools/api/design.php';
		loadTemplate(true);
		?>
	</head>
<body>
	<?php
	if ($object == null) {
		require_once '../.tools/scripts/utilities.php';
		redirect_to_url($redirect_url);
		return;
	}
	if (strlen($title) <= 1 && !is_alpha_numeric($title)) {
		redirect_to_url("../");
		return;
	}
	include("../.tools/extensions/navigation.php");
	?>

	<div class="area">
	<div class="area_logo">
		<?php
		$image = htmlspecialchars($object->images["rules"]);
		echo ($image != null && $image != "none" ? "<img src='data:image/png;base64,$image'>" : "<div class='paper'> <ul> <li class='paper_top'></li> <li></li> <li></li> <li></li> <li></li> <li></li> <li></li> <li></li> <li></li> <li></li> </ul> </div>");
		?>
	</div>
		<div class="area_title">
			<?php echo htmlspecialchars($object->names["rules"]); ?>
		</div>
		<div class="area_text">
			<?php echo htmlspecialchars($object->descriptions["rules"]); ?>
		</div>
	</div>

	<div class="area" id="darker">
		<div class="area_list" id="legal">
			<ul>
				<?php
			  	$counter = 1;

				foreach ($object->rules as $value) {
					loadRule($counter, $value->information);
					$counter++;
				}
				?>
			</ul>
		</div>
	</div>

	<?php
	$anticheat = getAntiCheat($object->subdomain);

	if ($anticheat != null) { ?>
	<div class="area">
		<div class="area_logo">
			<?php
			$image = htmlspecialchars($object->images["anticheat"]);
			echo ($image != null && $image != "none" ? "<img src='data:image/png;base64,$image'>" : "<div class='circle'></div>");
			?>
		</div>
		<div class="area_title">
			<?php echo htmlspecialchars($object->names["anticheat"]); ?>
		</div>
		<div class="area_text">
			<?php echo htmlspecialchars($object->descriptions["anticheat"]); ?>
			<p>
			<b>Logged Information</b> <?php echo $anticheat->logs; ?>
			<br>
			<b>Suspicions Made</b> <?php echo $anticheat->violations; ?>
			<br>
			<b>Mistakes Corrected</b> <?php echo $anticheat->false_positives; ?>
			<br>
			<b>Punishments Executed</b> <?php echo $anticheat->punishments; ?>
			<br>
			<b>Player Reports</b> <?php echo $anticheat->reports; ?>
		</div>
	</div>
	<?php } ?>

	<?php include("../.tools/extensions/footer.php"); ?>
</body>
</html>
