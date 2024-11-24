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

class AccountVariables
{

    public const
        SESSIONS_TABLE = "session.sessions",
        INSTANT_LOGINS_TABLE = "session.instantLogins",

        ACCOUNTS_TABLE = "account.accounts",
        ACCOUNT_IDENTIFICATION_TABLE = "account.identification",
        ACCOUNT_HISTORY_TABLE = "account.history",

        ACCOUNT_NOTIFICATIONS_TABLE = "notification.notifications",
        ACCOUNT_NOTIFICATION_TYPES_TABLE = "notification.types",

        ROLE_PERMISSIONS_TABLE = "management.permissions",
        ROLES_TABLE = "management.roles",
        FUNCTIONALITIES_TABLE = "management.functionalities",
        BLOCKED_FUNCTIONALITIES_TABLE = "management.blockedFunctionalities",
        ACCOUNT_PERMISSIONS_TABLE = "management.accountPermissions",
        ACCOUNT_ROLES_TABLE = "management.accountRoles",
        ACCOUNT_SETTINGS_TABLE = "management.settings",

        MODERATIONS_TABLE = "moderation.moderations",
        EXECUTED_MODERATIONS_TABLE = "moderation.executedModerations",

        ACCOUNT_INSTANT_COOLDOWNS_TABLE = "cooldown.instantCooldowns",
        ACCOUNT_BUFFER_COOLDOWNS_TABLE = "cooldown.bufferCooldowns",

        PRODUCTS_TABLE = "product.products",
        PRODUCT_COUPONS_TABLE = "product.coupons",
        PRODUCT_DOWNLOADS_TABLE = "product.downloads",
        PRODUCT_DIVISIONS_TABLE = "product.productDivisions",
        PRODUCT_PURCHASES_TABLE = "product.purchases",
        PRODUCT_COMPATIBILITIES_TABLE = "product.compatibilities",
        PRODUCT_BUTTONS_TABLE = "product.buttons",
        PRODUCT_CARDS_TABLE = "product.cards",
        PRODUCT_IDENTIFICATION_TABLE = "product.identification",
        PRODUCT_UPDATES_TABLE = "product.updates",
        PRODUCT_TRANSACTION_SEARCH_TABLE = "product.transactionSearch",
        PRODUCT_TRANSACTION_SEARCH_EXECUTIONS_TABLE = "product.transactionSearchExecutions",
        PRODUCT_TIERS_TABLE = "product.tiers",

        PRODUCT_GIVEAWAYS_TABLE = "giveaway.giveaways",
        GIVEAWAY_WINNERS_TABLE = "giveaway.winners",

        UNKNOWN_EMAIL_PROCESSING_TABLE = "transactions.unknownEmailProcessing",

        ADDED_ACCOUNTS_TABLE = "correlation.addedAccounts",
        ACCEPTED_ACCOUNTS_TABLE = "correlation.accounts",

        TICKETS_EMAIL_TABLE = "tickets.email",

        EMAIL_VERIFICATIONS_TABLE = "verification.emailVerifications",
        CHANGE_PASSWORD_TABLE = "verification.changePassword",

        STATISTIC_TYPES_TABLE = "statistic.types",
        STATISTIC_INTEGERS_TABLE = "statistic.integerStatistics",
        STATISTIC_LONG_TABLE = "statistic.longStatistics",
        STATISTIC_DOUBLE_TABLE = "statistic.doubleStatistics",
        STATISTIC_STRING_TABLE = "statistic.stringStatistics",
        STATISTIC_BOOLEAN_TABLE = "statistic.booleanStatistics",

        INSTRUCTIONS_LOCAL_TABLE = "instructions.local",
        INSTRUCTIONS_PUBLIC_TABLE = "instructions.public",
        INSTRUCTIONS_REPLACEMENTS_TABLE = "instructions.replacements";
}
