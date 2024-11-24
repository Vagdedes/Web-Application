<?php

class EmailVariables
{
    public const
        EXECUTIONS_TABLE = "email.executions",
        FAILED_EXECUTIONS_TABLE = "email.failedExecutions",
        PLANS_TABLE = "email.plans",
        STORAGE_TABLE = "email.storage",
        EXEMPTIONS_TABLE = "email.exemptions",
        USER_EXEMPTION_KEYS_TABLE = "email.exemptionKeys",
        BLACKLIST_TABLE = "email.blacklist";

    public const
        DEFAULT_COMPANY_NAME = "Idealistic AI",
        EXEMPT_TOKEN_LENGTH = 1024,
        CREDENTIALS_DIRECTORY = "email_credentials";

    public const
        email_credential_lines = 6,
        IDEALISTIC_CONTACT = 2,
        IDEALISTIC_NO_REPLY = 4;
}

$email_default_email_name = "contact@" . get_domain(false);