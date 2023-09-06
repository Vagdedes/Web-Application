<?php

$private_key = get_form_get("private_key");
$value = get_form_get("value");
$action = get_form_get("action");

if (strlen($private_key) == $private_key_length && ($action == "add" && strlen($value) > 0) && strpos($_SERVER['HTTP_USER_AGENT'], "MinecraftServerWebsite") !== false) {
	$object = getObject(null, $private_key);

	if ($object != null) {
		$subdomain = $object->subdomain;
		$subdomain = $sql_connection->real_escape_string($subdomain);

		if ($action == "add") {
			$split = explode($separator, $value);

			if (sizeof($split) == 4) {
				$logs = $split[0];
				$violations = $split[1];
				$false_positives = $split[2];
				$punishments = $split[3];

				if (is_numeric($logs) && is_numeric($violations) && is_numeric($false_positives) && is_numeric($punishments)) {
					$table = "anticheat";
					$query = get("subdomain", "=", $subdomain, $table);

					if (isset($query->num_rows) && $query->num_rows > 0) {
						sql_query("UPDATE $table SET logs = '$logs', violations = '$violations', false_positives = '$false_positives', punishments = '$punishments' WHERE subdomain = '$subdomain';");
					} else {
						sql_insert_old(array("subdomain", "logs", "violations", "false_positives", "punishments", "reports"),
                            array($subdomain, $logs, $violations, $false_positives, $punishments, 0),
                            $table);
					}
					echo "true";
				} else {
					echo "false";
				}
			} else if (sizeof($split) == 5) {
				$logs = $split[0];
				$violations = $split[1];
				$false_positives = $split[2];
				$punishments = $split[3];
				$reports = $split[4];

				if (is_numeric($logs) && is_numeric($violations) && is_numeric($false_positives) && is_numeric($punishments) && is_numeric($reports)) {
					$table = "anticheat";
					$query = get("subdomain", "=", $subdomain, $table);

					if (isset($query->num_rows) && $query->num_rows > 0) {
						sql_query("UPDATE $table SET logs = '$logs', violations = '$violations', false_positives = '$false_positives', punishments = '$punishments', reports = '$reports' WHERE subdomain = '$subdomain';");
					} else {
						sql_insert_old(array("subdomain", "logs", "violations", "false_positives", "punishments", "reports"),
                            array($subdomain, $logs, $violations, $false_positives, $punishments, $reports),
                            $table);
					}
					echo "true";
				} else {
					echo "false";
				}
			} else {
				echo "false";
			}
		}
	} else {
		echo "false";
	}
}
