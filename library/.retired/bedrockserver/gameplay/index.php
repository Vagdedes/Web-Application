<html>
	<head>
		<title>
			<?php
			require_once '../.tools/api/object.php';
			$object = getObject(explode(".", $_SERVER['SERVER_NAME']), null);
			$title = htmlspecialchars($object->names["gameplay"]);
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
			$image = htmlspecialchars($object->images["gameplay"]);
			echo ($image != null && $image != "none" ? "<img src='data:image/png;base64,$image'>" : "<div class='console'><ul><li></li><li class=console_margin></li><li></li> <li></li><li class=console_margin></li> <li></li><li></li> <li class=console_margin></li> <li></li></ul></div>");
			?>
		</div>
		<div class="area_title">
			<?php echo htmlspecialchars($object->names["gameplay"]); ?>
		</div>
		<div class="area_text">
			<?php echo htmlspecialchars($object->descriptions["gameplay"]); ?>
		</div>
	</div>

	<?php
	$gameplay = $object->gameplays;
	$counter = 0;
	$size = sizeof($gameplay);

	foreach ($gameplay as $value) {
		$counter++;
		loadDivision($value->title, $value->description, $value->image, !($counter == $size && $counter % 2 != 0));
	}
	include("../.tools/extensions/footer.php");
	?>
</body>
</html>
