<?php
$image = htmlspecialchars($object->images["background"]);
$image = $image != null && $image != "none" ? "data:image/png;base64,$image" : "/.images/background.png";
?>
<style>
	.navigation div {
    	background-image: url(<?php echo $image; ?>);
		background-color: #212121;
	}
	.navigation div img {
		background-color: #212121;
	}
</style>
<div class="navigation">
	  <?php
	  //$img = "<img src='$image'>";
	  $serverAddress = explode(":", $object->server_address);
	  $status = (new MinecraftServerStatus(htmlspecialchars($serverAddress[0], sizeof($serverAddress) > 1 ? $serverAddress[1] : "25565")))->online_players;
	  $online_players = "<i class='fas fa-user-friends' style='color: #ffc107;'></i> " . (isset($status) ? $status : 0) . " " . $object->names["online_players"];
	  $unique_players = "<i class='fas fa-user-cog' style='color: #ffc107;'></i> " . getUniquePlayers($object->subdomain) . " " . $object->names["unique_players"];
	  $store = "<i class='fas fa-shopping-bag' style='color: #ffc107;'></i> " . $object->name . " " . $object->names["store"];
	  $ip = "<i class='fas fa-server' style='color: #ffc107;'></i> IP: " . $object->server_address;
	  $store_url = $object->store_url != null ? $object->store_url : "#";
	  echo "
	  <div>
	  <a href='#' class='button' id='red' style='float: left;'>$ip</a>
	  <a href='$store_url' class='button' id='blue' style='float: right;'>$store</a>
	  </div>";
	  ?>
	<ol>
		<?php
		echo "<li>" . $online_players . "</li>";

		if ($object->is_patreon) {
			echo "<li>" . $unique_players . "</li>";
		}
		?>
	</ol>
	<ul>
		<?php
		$name = htmlspecialchars($object->names["home"]);

		if (strlen($name) > 1 || is_alpha_numeric($name)) {
			echo "<li><a href='/'>$name</a></li>";
		}

		$name = htmlspecialchars($object->names["gameplay"]);

		if (strlen($name) > 1 || is_alpha_numeric($name)) {
			echo "<li><a href='/gameplay'>$name</a></li>";
		}
		$name = htmlspecialchars($object->names["leaderboard"]);

		if (strlen($name) > 1 || is_alpha_numeric($name)) {
			echo "<li><a href='/leaderboard'>$name</a></li>";
		}
		$name = htmlspecialchars($object->names["staff_team"]);

		if (strlen($name) > 1 || is_alpha_numeric($name)) {
			echo "<li><a href='/staff'>$name</a></li>";
		}
		$name = htmlspecialchars($object->names["rules"]);

		if (strlen($name) > 1 || is_alpha_numeric($name)) {
			echo "<li><a href='/rules'>$name</a></li>";
		}

		if ($object->is_patreon && $object->support_email != null) {
			$name = htmlspecialchars($object->names["support"]);

			if (strlen($name) > 1 || is_alpha_numeric($name)) {
				echo "<li><a href='/support'>$name</a></li>";
			}
		}
		?>
		<li>
			<form action="/stats/">
				<input type="text" name="search" placeholder="Search Player" minlength=1 maxlength=32>
			</form>
		</li>
	</ul>
</div>
