<html>
	<head>
		<title>
			<?php
			require_once '../.tools/api/object.php';
			$object = getObject(explode(".", $_SERVER['SERVER_NAME']), null);
			$title = htmlspecialchars($object->names["leaderboard"]);
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
	require_once '../.tools/scripts/form.php';
	include("../.tools/extensions/navigation.php");
	?>

	<div class="area">
		<div class="area_logo">
			<?php
			$image = htmlspecialchars($object->images["leaderboard"]);
			echo ($image != null && $image != "none" ? "<img src='data:image/png;base64,$image'>" : "<div class='triangle'></div>");
			?>
		</div>
		<div class="area_title">
			<?php echo htmlspecialchars($object->names["leaderboard"]); ?>
		</div>
		<div class="area_text">
			<?php echo htmlspecialchars($object->descriptions["leaderboard"]); ?>
		</div>
		<div class="area_form">
			<?php
			$hasLeaderboard = false;
			foreach ($object->gameplays as $value) {
				if (getLeaderboardSize($object->subdomain, $value->id) > 0) {
					$hasLeaderboard = true;
					$value = htmlspecialchars($value->title);
					echo "<a href='?search=$value' class='button' id='red'>$value</a> ";
				}
			}
			?>
		</div>
	</div>

	<?php
	$search = htmlspecialchars(get_form_get("search"));
	$length = strlen($search) > 0;
	$access = $length;
	$gameplay = null;

	if ($access) {
		foreach ($object->gameplays as $value) {
			if ($value->title == $search) {
				$gameplay = $value;
				break;
			}
		}
		$access = $gameplay != null;
	}

	if ($access) {
	?>

	<div class="area" id="darker">
		<?php
		$leaderboard = getLeaderboard($object->subdomain, $gameplay->id);

		if (sizeof($leaderboard) > 0) {
		?>
			<div class="area_board">
				<ul>
					<li class="label">
						<div class="main">Player</div><div>Position</div><div>Score</div>
					</li>
					<?php
						$counter = 0;

						foreach ($leaderboard as $key => $value) {
							if (loadLeaderboardPlayer($key, $gameplay->title, $value, $counter + 1)) {
								$counter++;
							}
						}
					?>
				</ul>
			</div>
		<?php
		} else {
			$no_results = htmlspecialchars($object->descriptions["no_results"]);
			echo "<div class='area_title'>$no_results</div>";
		}
		?>
	</div>

	<?php
	} else {
		$reply = $hasLeaderboard ? htmlspecialchars($object->descriptions["selection"]) : htmlspecialchars($object->descriptions["no_results"]);
		echo "<div class='area' id='darker'><div class='area_title'>$reply</div></div>";
	}
	include("../.tools/extensions/footer.php");
	?>
</body>
</html>
