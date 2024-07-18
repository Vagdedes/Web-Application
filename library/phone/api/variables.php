<?php
$twilio_credentials_directory = "/var/www/.structure/private/twilio_credentials";

$phone_executions_table = "phone.executions";
$phone_failed_executions_table = "phone.failedExecutions";
$phone_plans_table = "phone.plans";
$phone_storage_table = "phone.storage";
$phone_exemptions_table = "phone.exemptions";
$phone_blacklist_table = "phone.blacklist";

$phone_default_company_name = "Idealistic AI";
$phone_default_email_name = "contact@" . get_domain();