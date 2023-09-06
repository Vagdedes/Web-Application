<?php
$oldSystem = isset($_GET["name"]);

$user_id = get_form_get("user_id");
$name = get_form_get($oldSystem ? "name" : "alphanumeric_name");
$version = get_form_get("version");
$subdomain = strtolower($oldSystem ? get_form_get("subdomain") : make_alpha_numeric($name));
$server_address = get_form_get("server_address");
$user_id_included = strlen($user_id) > 0;

if (strlen($name) > 0 && strlen($name) <= $name_length
	&& strlen($version) > 0 && strlen($version) <= $version_length
	&& strlen($subdomain) > 0 && strlen($subdomain) <= $subdomain_length
	&& strlen($server_address) >= 4
	&& strpos($_SERVER['HTTP_USER_AGENT'], "MinecraftServerWebsite") !== false) {
	$object = getObject($subdomain, null);

	if ($object == null && !in_array($subdomain, $blacklisted_subdomains)) {
		$ip_address = get_client_ip_address();
		$queryID = $user_id_included ? get("user_id", "=", $user_id, "websites") : null;
		$queryIP = get("access_address", "=", $ip_address, "websites");
		$queryLimit = getWebsiteLimit($object);

		if (isset($queryID->num_rows) && $queryID->num_rows >= $queryLimit
            || isset($queryIP->num_rows) && $queryIP->num_rows >= $queryLimit) {
			echo "limit";
		} else if (!is_alpha_numeric($subdomain)) {
			echo "alphanumeric" . $separator . "subdomain";
		} else {
			$user_id = $sql_connection->real_escape_string($user_id);
			$name = $sql_connection->real_escape_string($name);
			$subdomain = $sql_connection->real_escape_string($subdomain);
			$server_address = $sql_connection->real_escape_string($server_address);
			$private_key = random_string($private_key_length);

			if (sql_query($user_id_included ?
							 "INSERT INTO websites (user_id, private_key, template, name, subdomain, server_address, access_address, version)
					VALUES ('$user_id', '$private_key', '$templates[0]', '$name', '$subdomain', '$server_address', '$ip_address', '$version');" :
							"INSERT INTO websites (private_key, template, name, subdomain, server_address, access_address, version)
					VALUES ('$private_key', '$templates[0]', '$name', '$subdomain', '$server_address', '$ip_address', '$version');") == true) {
				addRecord("bedrockserver.com", "CNAME", $subdomain, "msw.vagdedes.com", 1, 10, "true");
				$keys = "";
				$values = "";

				foreach ($names as $value) {
					$keys .= $value . ", ";
				}
				foreach ($default_names as $value) {
					$values .= "'" . $value . "', ";
				}
				$keys = "subdomain, " . substr($keys, 0, -2);
				$values = "'$subdomain', " . substr($values, 0, -2);

				if (sql_query("INSERT INTO names ($keys) VALUES ($values);") == true) {
					$keys = "";
					$values = "";

					foreach ($descriptions as $value) {
						$keys .= $value . ", ";
					}
					foreach ($default_descriptions as $value) {
						$values .= "'" . $value . "', ";
					}
					$keys = "subdomain, " . substr($keys, 0, -2);
					$values = "'$subdomain', " . substr($values, 0, -2);

					if (sql_query("INSERT INTO descriptions ($keys) VALUES ($values);") == true) {
						$keys = "";
						$values = "";

						foreach ($categories as $value) {
							$keys .= $value . ", ";
						}
						foreach ($default_categories as $value) {
							$values .= "'" . $value . "', ";
						}
						$keys = "subdomain, Score, " . substr($keys, 0, -2);
						$values = "'$subdomain', 'Score', " . substr($values, 0, -2);

						if (sql_query("INSERT INTO categories ($keys) VALUES ($values);") == true) {
							if (sql_query("INSERT INTO images (subdomain) VALUES ('$subdomain');") == true) {
								if (sql_query("INSERT INTO colors (subdomain) VALUES ('$subdomain');") == true) {
									foreach ($default_gameplays as $parent => $value) {
										if (sql_query("INSERT INTO gameplays (subdomain, title, description) VALUES ('$subdomain', '$parent', '$value');") != true) {
											echo "false #7";
											return;
										}
									}
									foreach ($default_rules as $value) {
										if (sql_query("INSERT INTO rules (subdomain, information) VALUES ('$subdomain', '$value');") != true) {
											echo "false #8";
											return;
										}
									}
									if (sql_query("INSERT INTO chat_protection (subdomain, chat_cooldown, command_cooldown) VALUES ('$subdomain', '2', '1');") != true) {
										echo "false #9";
										return;
									}
									if (sql_query("INSERT INTO permissions (subdomain) VALUES ('$subdomain');") != true) {
										echo "false #10";
										return;
									}
									echo $private_key;
								} else {
									echo "false #6";
								}
							} else {
								echo "false #5";
							}
						} else {
							echo "false #4";
						}
					} else {
						echo "false #3";
					}
				} else {
					echo "false #2";
				}
			} else {
				echo "false #1";
			}
		}
	} else {
		echo "taken" . $separator . "subdomain";
	}
}
