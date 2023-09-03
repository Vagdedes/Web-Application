<?php
$private_key = get_form_post("private_key");
$key = get_form_post("key");
$value = get_form_post("value");

if (strlen($private_key) == $private_key_length && in_array($key, $images) && strlen($value) > 0 && strpos($_SERVER['HTTP_USER_AGENT'], "MinecraftServerWebsite") !== false) {
	$object = getObject(null, $private_key);

	if ($object != null) {
		$table = "images";
		$subdomain = $object->subdomain;
		$key = $sql_connection->real_escape_string($key);
		$value = $sql_connection->real_escape_string($value);

		sql_query("UPDATE $table SET $key = '$value' WHERE subdomain = '$subdomain';");
		echo "true";
	} else {
		echo "false";
	}
}
