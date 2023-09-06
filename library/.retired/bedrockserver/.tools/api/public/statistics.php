<?php
$action = get_form_get("action");
$private_key = get_form_get("private_key");
$uuid = get_form_get("uuid");
$gameplay = get_form_get("gameplay");
$key = get_form_get("key");
$value = get_form_get("value");
$score = get_form_get("score");

//array_push($categories, "score");
array_push($categories, "staff");
array_push($categories, "last_name");
array_push($categories, "join_date");
$keyIncluded = in_array($key, $categories);
$reset = $action == "reset";

if (strlen($private_key) == $private_key_length
	&& ($reset || strlen($uuid) == 36)
	&& strlen($gameplay) > 0 && !is_numeric($gameplay)
	&& ($action == "add" && $keyIncluded && strlen($value) > 0
	|| $action == "get" && $keyIncluded
	|| $action == "remove"
	|| $action == "exists"
	|| $reset)
	&& strpos($_SERVER['HTTP_USER_AGENT'], "MinecraftServerWebsite") !== false) {
	$object = getObject(null, $private_key);

	if ($object != null) {
		$table = "statistics";
		$subdomain = $object->subdomain;
		$exists = false;

		foreach ($object->gameplays as $obj) {
			if ($obj->title == $gameplay) {
				$gameplay = $obj->id;
				break;
			}
		}

		if (is_numeric($gameplay)) {
			$query = null;

			if (!$reset) {
				$uuid = $sql_connection->real_escape_string($uuid);
				$query = sql_query("SELECT * FROM statistics WHERE uuid = '$uuid' AND gameplay_id = '$gameplay' AND subdomain = '$subdomain';");

				if (isset($query->num_rows) && $query->num_rows > 0) {
					$exists = true;
				}
			} else {
				sql_query("DELETE FROM $table WHERE subdomain = '$subdomain' AND gameplay_id = '$gameplay';");
				echo "true";
				return;
			}
			$key = $sql_connection->real_escape_string($key);
			$value = $sql_connection->real_escape_string($value);

			if ($action == "add") {
				if (!$exists) {
					if (getUniquePlayers($subdomain) >= getStatisticsLimit($object)) {
						echo "limit";
					} else {
						sql_query("INSERT INTO $table (subdomain, uuid, gameplay_id, $key) VALUES ('$subdomain', '$uuid', '$gameplay', '$value');");
						echo "true";
					}
				} else {
					$scoreText = "";

					if (strlen($score) > 0) {
						$scoreText = ", score = '" . getScore($subdomain, $uuid, $gameplay) . "'";
					}
					sql_query("UPDATE $table SET $key = '$value'$scoreText WHERE subdomain = '$subdomain' AND uuid = '$uuid' AND gameplay_id = '$gameplay';");
					echo "true";
				}
			} else if ($action == "get") {
				if ($exists) {
					// $query = query("SELECT * FROM statistics WHERE uuid = '$uuid' AND gameplay_id = '$gameplay' AND subdomain = '$subdomain';");
					// Query already made previously

					if ($query != null) {
						while ($row = $query->fetch_assoc()) {
							if ($keyIncluded) {
								$column = $row[$key];
								echo ($column == null || strlen($column) == 0 ? "none" : $column);
							} else {
								$divisor = " % ";
								$divisorLength = strlen($divisor);
								$string = "";

								foreach ($categories as $category) {
									$column = $row[$category];
									$string .= ($column == null || strlen($column) == 0 ? "none" : $column) . $divisor;
								}
								if (strlen($string) > $divisorLength) {
									echo substr($string, 0, -$divisorLength);
								} else {
									echo "none";
								}
							}
							break;
						}
					} else {
						echo "none";
					}
				} else {
					echo "none";
				}
			} else if ($action == "remove") {
				if ($exists) {
					if ($keyIncluded) {
						sql_query("UPDATE $table SET $key = NULL WHERE subdomain = '$subdomain' AND uuid = '$uuid' AND gameplay_id = '$gameplay';");
					} else {
						sql_query("DELETE FROM $table WHERE subdomain = '$subdomain' AND uuid = '$uuid' AND gameplay_id = '$gameplay';");
					}
					echo "true";
				} else {
					echo "false";
				}
			} else if ($action == "exists") {
				echo ($exists ? "true" : "false");
			} // No need for else statement, allowance is specified on an earlier level
		} else {
			echo "false";
		}
	} else {
		echo "false";
	}
}
