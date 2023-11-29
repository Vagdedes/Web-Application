<?php
$discord_webhook_executions_table = "discord.webhookExecutions";
$discord_webhook_failed_executions_table = "discord.webhookFailedExecutions";
$discord_webhook_plans_table = "discord.webhookPlans";
$discord_webhook_storage_table = "discord.webhookStorage";
$discord_webhook_blacklist_table = "discord.webhookBlacklist";
$discord_webhook_exemptions_table = "discord.webhookExemptions";

$discord_webhook_default_company_name = "Idealistic AI";
$discord_webhook_default_email_name = "contact@" . get_domain(false);

$discord_webhooks_accepted_domains = array(
    "discord.com",
    "discordapp.com"
);

