<?php
// Administrator
if (!isset($administrator_local_server_ip_addresses_table)) { // Already set by communication script
    $administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";
}
$administrator_public_server_ip_addresses_table = "administrator.publicServerIpAddresses";
$administrator_application_api_connections_table = "administrator.applicationApiConnections";
$administrator_application_api_keys_table = "administrator.applicationApiKeys";
$administrator_application_api_paths_table = "administrator.applicationApiPaths";
$administrator_application_api_extra_table = "administrator.applicationExtraInformation";
$administrator_applications_table = "administrator.applications";