<?php
$private_key = get_form_get("private_key");
$key = get_form_get("key");
$value = get_form_get("value");

if (strlen($private_key) == $private_key_length && in_array($key, $settings) && strlen($value) > 0 && strpos($_SERVER['HTTP_USER_AGENT'], "MinecraftServerWebsite") !== false) {
	if (strlen($value) <= $settingsLengths[$key] && ($key != "template" || in_array($value, $templates))) {
		$object = getObject(null, $private_key);

		if ($object != null) {
			$subdomain = $key == "subdomain";
			$private_key = $sql_connection->real_escape_string($private_key);
			$key = $sql_connection->real_escape_string($key);
			$value = $sql_connection->real_escape_string($value);

			if ($subdomain) {
				if ($object->is_patreon) {
					$value = strtolower($value);

					if (is_alpha_numeric($value)) {
						$query = get("subdomain", "=", $value, "websites");

						if (isset($query->num_rows) && $query->num_rows > 0
                            || in_array($value, $blacklisted_subdomains)) {
							echo "taken";
							return;
						}
					} else {
						echo "alphanumeric";
						return;
					}
				} else {
					echo "limit";
					return;
				}
			}
			if (sql_query("UPDATE websites SET $key = '$value' WHERE private_key = '$private_key';") == true) {
				if ($subdomain) {
					$oldSubdomain = $object->subdomain;
					deleteRecord("bedrockserver.com", $oldSubdomain);
					addRecord("bedrockserver.com", "CNAME", $value, "msw.vagdedes.com", 1, 10, "true");
					$ignoreMainTable = false;

					foreach ($tables as $table) {
						if ($ignoreMainTable) {
							if (sql_query("UPDATE $table SET $key = '$value' WHERE subdomain = '$oldSubdomain';") != true) {
								echo "false";
								break;
							}
						} else {
							$ignoreMainTable = true;
						}
					}
				}
				echo "true";
			} else {
				echo "false";
			}
		} else {
			echo "false";
		}
	} else {
		echo "length" . $separator . $key . $separator . $settingsLengths[$key];
	}
}
