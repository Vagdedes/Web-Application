<?php
// Administrator
if (!isset($administrator_local_server_ip_addresses_table)) { // Already set by communication script
    $administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";
}
$administrator_public_server_ip_addresses_table = "administrator.publicServerIpAddresses";

// Session
$account_sessions_table = "session.sessions";
$instant_logins_table = "session.instantLogins";

// Account
$accounts_table = "account.accounts";
$account_identification_table = "account.identification";
$account_history_table = "account.history";

// Notification
$account_notifications_table = "notification.notifications";
$account_notification_types_table = "notification.types";

// Management
$role_permissions_table = "management.permissions";
$roles_table = "management.roles";
$functionalities_table = "management.functionalities";
$blocked_functionalities_table = "management.blockedFunctionalities";
$account_permissions_table = "management.accountPermissions";
$account_roles_table = "management.accountRoles";
$account_settings_table = "management.settings";

// Moderation
$moderations_table = "moderation.moderations";
$executed_moderations_table = "moderation.executedModerations";

// Cooldown
$account_instant_cooldowns_table = "cooldown.instantCooldowns";
$account_buffer_cooldowns_table = "cooldown.bufferCooldowns";

// Product
$products_table = "product.products";
$product_coupons_table = "product.coupons";
$product_downloads_table = "product.downloads";
$product_divisions_table = "product.productDivisions";
$product_purchases_table = "product.purchases";
$product_compatibilities_table = "product.compatibilities";
$product_buttons_table = "product.buttons";
$product_cards_table = "product.cards";
$product_identification_table = "product.identification";
$product_updates_table = "product.updates";
$product_transaction_search_table = "product.transactionSearch";
$product_transaction_search_executions_table = "product.transactionSearchExecutions";
$product_tiers_table = "product.tiers";

// Giveaway
$product_giveaways_table = "giveaway.giveaways";
$giveaway_winners_table = "giveaway.winners";

// Transactions
$unknown_email_processing_table = "transactions.unknownEmailProcessing";

// Correlation
$added_accounts_table = "correlation.addedAccounts";
$accepted_accounts_table = "correlation.accounts";

// Tickets
$tickets_email_table = "tickets.email";

// Verification
$email_verifications_table = "verification.emailVerifications";
$change_password_table = "verification.changePassword";

// Statistic
$statistic_types_table = "statistic.types";
$statistic_integers_table = "statistic.integerStatistics";
$statistic_long_table = "statistic.longStatistics";
$statistic_double_table = "statistic.doubleStatistics";
$statistic_string_table = "statistic.stringStatistics";
$statistic_boolean_table = "statistic.booleanStatistics";

// E.T.C
$website_domain = "https://" . get_domain();

class InstructionsTable
{
    public const
        LOCAL = "instructions.local",
        PUBLIC = "instructions.public",
        REPLACEMENTS = "instructions.replacements";
}
