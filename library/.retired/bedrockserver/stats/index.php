<html>
	<head>
		<title>
			<?php
			$object = getObject(explode(".", $_SERVER['SERVER_NAME']), null);
			$title = htmlspecialchars($object->names["stats"]);
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
	$player = strip_tags(get_form_get("search"));

	if (strlen($player) == 0) {
		redirect_to_url("..");
		return;
	}
	include("../.tools/extensions/navigation.php");
	$gameplaySearch = get_form_get("gameplay");
	$access = strlen($gameplaySearch) > 0;
	$gameplay = null;
	$darker = false;
	$playerUUID = null;

	if ($access) {
		foreach ($object->gameplays as $value) {
			if ($value->title == $gameplaySearch) {
				$gameplay = $value;
				break;
			}
		}
		$access = $gameplay != null;
	}

	if ($access) {
		$player = getStatistics($object->subdomain, $player, $gameplay->id);

		if ($player == null) {
			redirect_to_url("..");
			return;
		}
		$playerUUID = $player->uuid;
	?>

	<div class="area">
	<div class="area_logo">
		<?php
		$image = get_minecraft_head_image(str_replace("*", "", $player->name), 150);
		echo "<img src=$image>";
		?>
	</div>
		<div class="area_title">
			<?php
			$staff = $player->staff ? "<b>Staff</b> " : "";
			echo $staff . htmlspecialchars($player->name);
			?>
		</div>
		<div class="area_text">
			<?php
			$date = get_full_date(substr($player->join_date, 0, 10));
			echo "Member since " . $date;
			?>
		</div>
	</div>

	<div class="area" id="darker">
		<div class="area_list" id="legal">
			<ul>
				<?php
				$stats = $player->stats;
				foreach ($categories as $category) {
					echo "<li><div class='area_list_title'>" . htmlspecialchars($object->categories[$category]) . "</div>";
					$array = isset($stats[$category]) ? $stats[$category] : null;

					if (is_array($array) && sizeof($array) > 0) {
						foreach ($array as $list => $childArray) {
							$list = str_replace("_", " ", $list);
							$list = strtoupper(substr($list, 0, 1));
							echo "<div class='area_list_contents'><div class='area_list_contents_title'>" . htmlspecialchars($list) . "</div><div class='area_list_contents_box'>";
							$counter = 0;
							$size = sizeof($childArray);

							foreach ($childArray as $key => $value) {
								$counter++;
								$key = str_replace("_", " ", $key);
								$key = strtoupper(substr($key, 0, 1));
								echo "<b>" . htmlspecialchars($key) . "</b> " . htmlspecialchars($value) . ($counter < $size ? "<br>" : "");
							}
							echo "</div></div>";
						}
					} else {
						echo "<div class='area_list_contents'>" . htmlspecialchars($object->descriptions["no_results"]) . "</div>";
					}
					echo "</li>";
				}
				?>
			</ul>
		</div>
	</div>

	<?php
	} else {
		$darker = true;
		$playerUUID = getPlayerUUID($object->subdomain, $player);
	?>

	<div class="area">
	<div class="area_logo">
		<?php
		$image = get_minecraft_head_image("Steve", 150);
		echo "<img src=$image>";
		?>
	</div>
		<div class="area_title">
			<?php
			echo htmlspecialchars($player);
			?>
		</div>
		<div class="area_text">
			<?php
			$gameplays = array();

			foreach ($object->gameplays as $value) {
				if (getStatistics($object->subdomain, $player, $value->id) != null) {
					array_push($gameplays, $value);
				}
			}
			echo sizeof($gameplays) > 0 ? htmlspecialchars($object->descriptions["selection"]) : htmlspecialchars($object->descriptions["no_results"]);
			?>
		</div>
		<div class="area_form">
			<?php
			foreach ($gameplays as $value) {
				$value = htmlspecialchars($value->title);
				echo "<a href='?search=$player&gameplay=$value' class='button' id='red'>$value</a> ";
			}
			?>
		</div>
	</div>

	<?php
	}

	if ($playerUUID != null) {
		$punishments = getPunishments($object->subdomain, $playerUUID);

		if (sizeof($punishments) > 0) {
			if ($darker) {
				echo "<div class='area' id='darker'>";
			} else {
				echo "<div class='area'>";
			}
			?>
			<div class="area_board">
				<ul>
					<li class="label">
						<div class="main">Punishment Date</div><div>Type</div><div>Executor</div>
					</li>
					<?php
						foreach ($punishments as $value) {
							loadPunishment($value->creation, $value->type, $value->executor);
						}
					?>
				</ul>
			</div>
			</div>
		<?php
		}
	}

	include("../.tools/extensions/footer.php");
	?>
	</div>
</body>
</html>
