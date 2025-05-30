<?php
// Administrator
if (!isset($administrator_local_server_ip_addresses_table)) { // Already set by communication script
    $administrator_local_server_ip_addresses_table = "administrator.localServerIpAddresses";
}
$administrator_public_server_ip_addresses_table = "administrator.publicServerIpAddresses";

class AccountVariables
{

    public const
        SESSIONS_TABLE = "session.sessions",
        INSTANT_LOGINS_TABLE = "session.instantLogins",

        TEAM_MEMBERS_TABLE = "team.members",
        TEAM_TABLE = "team.teams",
        TEAM_OWNERS_TABLE = "team.owners",
        TEAM_PERMISSIONS_TABLE = "team.teamPermissions",
        TEAM_MEMBER_PERMISSIONS_TABLE = "team.memberPermissions",
        TEAM_PERMISSION_DEFINITIONS_TABLE = "team.permissionDefinitions",
        TEAM_NAME_CHANGES = "team.nameChanges",
        TEAM_ROLE_NAME_CHANGES = "team.roleNameChanges",
        TEAM_MEMBER_POSITIONS_TABLE = "team.memberPositions",
        TEAM_ROLES_TABLE = "team.roles",
        TEAM_ROLE_MEMBERS_TABLE = "team.roleMembers",
        TEAM_ROLE_POSITIONS_TABLE = "team.rolePositions",
        TEAM_ROLE_PERMISSIONS_TABLE = "team.rolePermissions",

        TRANSLATIONS_PROCESSED_TABLE = "translations.processed",

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
        PRODUCT_PURCHASES_TABLE = "product.purchases",
        PRODUCT_COMPATIBILITIES_TABLE = "product.compatibilities",
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
