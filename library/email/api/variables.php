<?php
$email_executions_table = "email.executions";
$email_failed_executions_table = "email.failedExecutions";
$email_plans_table = "email.plans";
$email_storage_table = "email.storage";
$email_exemptions_table = "email.exemptions";
$email_user_exemption_keys_table = "email.exemptionKeys";
$email_blacklist_table = "email.blacklist";

$email_default_company_name = "Idealistic AI";
$email_default_email_name = "contact@" . get_domain(false);

$email_exempt_token_length = 1024;

$email_credentials_directory = "email_credentials";

class EmailBase
{
    public const
        email_credential_lines = 8,
        VAGDEDES_CONTACT = 2,
        IDEALISTIC_CONTACT = 4,
        IDEALISTIC_NO_REPLY = 6;
}