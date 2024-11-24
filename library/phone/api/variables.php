<?php

class PhoneVariables
{
    public const
        EXECUTIONS_TABLE = "phone.executions",
        FAILED_EXECUTIONS_TABLE = "phone.failedExecutions",
        PLANS_TABLE = "phone.plans",
        STORAGE_TABLE = "phone.storage",
        EXEMPTIONS_TABLE = "phone.exemptions",
        BLACKLIST_TABLE = "phone.blacklist",

        TWILIO_CREDENTIALS_DIRECTORY = "twilio_credentials",
        DEFAULT_COMPANY_NAME = "Idealistic AI";
}

$phone_default_email_name = "contact@" . get_domain();