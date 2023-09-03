<?php
$private_key = get_form_get("private_key");
$key = get_form_get("key");
$value = get_form_get("value");

if (strlen($private_key) == $private_key_length && in_array($key, $colors) && strpos($_SERVER['HTTP_USER_AGENT'], "MinecraftServerWebsite") !== false) {
	if (strlen($value) <= $color_length) {
		$object = getObject(null, $private_key);

		if ($object != null) {
			$table = "colors";
			$subdomain = $object->subdomain;
			$key = $sql_connection->real_escape_string($key);
			$value = $sql_connection->real_escape_string($value);

			if (isColorValid($value)) {
				sql_query("UPDATE $table SET $key = '$value' WHERE subdomain = '$subdomain';");
			} else {
				sql_query("UPDATE $table SET $key = NULL WHERE subdomain = '$subdomain';");
			}
			echo "true";
		} else {
			echo "false";
		}
	} else {
		echo "length" . $separator . $key . $separator . $color_length;
	}
}
