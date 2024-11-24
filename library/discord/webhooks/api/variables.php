<?php

class DiscordWebhookVariables
{
    public const WEBHOOK_EXECUTIONS_TABLE = "discord.webhookExecutions",
        WEBHOOK_FAILED_EXECUTIONS_TABLE = "discord.webhookFailedExecutions",
        WEBHOOK_PLANS_TABLE = "discord.webhookPlans",
        WEBHOOK_STORAGE_TABLE = "discord.webhookStorage",
        WEBHOOK_BLACKLIST_TABLE = "discord.webhookBlacklist",
        WEBHOOK_EXEMPTIONS_TABLE = "discord.webhookExemptions";

    public const
        DEFAULT_COMPANY_NAME = "Idealistic AI",
        ACCEPTED_DOMAINS = array(
        "discord.gg",
        "discord.com",
        "discordapp.com"
    );
}

$discord_webhook_default_email_name = "contact@" . get_domain(false);

