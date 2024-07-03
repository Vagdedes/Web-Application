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

// Session
$account_sessions_table = "session.sessions";
$instant_logins_table = "session.instantLogins";

// Translation
$translation_languages_table = "translation.languages";
$translation_text_table = "translation.text";

// Knowledge
$knowledge_types_table = "knowledge.types";
$knowledge_information_table = "knowledge.information";

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
$product_reviews_table = "product.reviews";
$product_tiers_table = "product.tiers";

// Giveaway
$product_giveaways_table = "giveaway.giveaways";
$giveaway_winners_table = "giveaway.winners";

// Transactions
$unknown_email_processing_table = "transactions.unknownEmailProcessing";

// Affiliate
$affiliate_campaigns_table = "affiliate.campaign";
$affiliate_sharing_table = "affiliate.sharring";
$affiliate_types_table = "affiliate.types";

// Communication
$communication_contents_table = "communication.contents";
$communication_invitations_table = "communication.invitations";
$communication_threads_table = "communication.threads";
$communication_types_table = "communication.types";

// Cooperation
$cooperation_votings_table = "cooperation.votings";
$cooperation_invitations_table = "cooperation.invitations";
$cooperation_choices_table = "cooperation.choices";
$cooperation_selections_table = "cooperation.selections";

// Correlation
$added_accounts_table = "correlation.addedAccounts";
$accepted_accounts_table = "correlation.accounts";
$correlation_instants_table = "correlation.instant";
$correlation_requests_table = "correlation.requests";
$correlation_types_table = "correlation.types";

// Tickets
$tickets_email_table = "tickets.email";

// Files
$file_files_table = "file.files";
$file_sharing_table = "file.sharing";
$file_types_table = "file.types";
$file_downloads_table = "file.downloads";

// Team
$team_invitations_table = "team.invitations";
$team_modifications_table = "team.modifications";
$team_teams_table = "team.teams";

// Verification
$verification_instant_table = "verification.instant";
$verification_password_table = "verification.password";
$verification_types_table = "verification.types";
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
        BROWSE = "instructions.browse",
        REPLACEMENTS = "instructions.replacements";
}
