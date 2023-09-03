<html>
	<head>
		<title>
			<?php
			$object = getObject(explode(".", $_SERVER['SERVER_NAME']), null);
			$title = htmlspecialchars($object->names["staff_team"]);
			echo htmlspecialchars($object->name) . " | " . $title;
			?>
		</title>
		<link rel="shortcut icon" type="image/png" href="https://vagdedes.com/.images/bedrockIcon.png">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href='https://vagdedes.com/.css/universal.css?id="<?php echo rand(0, 2147483647)?>'>
		<link rel="stylesheet" href='https://vagdedes.com/.css/schemes.css?id="<?php echo rand(0, 2147483647)?>'>
		<script src="https://kit.fontawesome.com/44b5efe96d.js" crossorigin="anonymous"></script>
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
	if (strlen($title) <= 1 && !is_alpha_numeric($title)) {
		redirect_to_url("../");
		return;
	}
	include("../.tools/extensions/navigation.php");
	?>

	<div class="area">
	<div class="area_logo">
		<?php
		$image = htmlspecialchars($object->images["staff_team"]);
		echo ($image != null && $image != "none" ? "<img src='data:image/png;base64,$image'>" : "<div class='search'><ul><li class='search_top'></li><li class='search_bottom'></li></ul></div>");
		?>
	</div>
		<div class="area_title">
			<?php echo htmlspecialchars($object->names["staff_team"]); ?>
		</div>
		<div class="area_text">
			<?php echo htmlspecialchars($object->descriptions["staff_team"]); ?>
		</div>
	</div>

	<?php
	$staff = getStaff($object->subdomain);

	if (sizeof($staff) > 0) {
		$loaded = 0;

		foreach ($staff as $player) {
			if (loadStaff($player, 25)) {
				$loaded++;
			}
		}
		if ($loaded > 0) {
			$remaining = 4 - ($loaded % 4);

			for ($i = 0; $i < $remaining; $i++) {
				loadEmptyStaff(25);
			}
		} else {
			$no_results = htmlspecialchars($object->descriptions["no_results"]);
			echo "<div class='area' id='darker'><div class='area_title'>$no_results</div></div>";
		}
	} else {
		$no_results = htmlspecialchars($object->descriptions["no_results"]);
		echo "<div class='area' id='darker'><div class='area_title'>$no_results</div></div>";
	}
	include("../.tools/extensions/footer.php");
	?>
</body>
</html>
