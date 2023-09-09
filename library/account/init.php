<?php

// Base
require_once '/var/www/.structure/library/account/api/connect.php';
require_once '/var/www/.structure/library/account/api/variables.php';

// Abstract
require_once '/var/www/.structure/library/account/api/objects/abstract/MinecraftPlatform.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/MethodReply.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/ParameterVerification.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/AccountPatreon.php';

// Account
require_once '/var/www/.structure/library/account/api/objects/account/AccountHistory.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountSettings.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountTransactions.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountActions.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountObjectives.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountIdentification.php';

// Product
require_once '/var/www/.structure/library/account/api/objects/product/AccountProductDownloads.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountPurchases.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountReviews.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountGiveaway.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountOffer.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountProduct.php';
require_once '/var/www/.structure/library/account/api/objects/product/ProductCoupon.php';

// Management
require_once '/var/www/.structure/library/account/api/objects/management/AccountModerations.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountPermissions.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountFunctionality.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountRole.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountCooldowns.php';

// Credential
require_once '/var/www/.structure/library/account/api/objects/credential/AccountAccounts.php';
require_once '/var/www/.structure/library/account/api/objects/credential/AccountPassword.php';
require_once '/var/www/.structure/library/account/api/objects/credential/AccountFiles.php';
require_once '/var/www/.structure/library/account/api/objects/credential/AccountVerification.php';

// Communication
require_once '/var/www/.structure/library/account/api/objects/communication/AccountEmail.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountNotifications.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountPhoneNumber.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountTeam.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountCommunication.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountCooperation.php';

// Correlation
require_once '/var/www/.structure/library/account/api/objects/correlation/AcceptedAccount.php';
require_once '/var/www/.structure/library/account/api/objects/correlation/AccountCorrelation.php';

// Finance
require_once '/var/www/.structure/library/account/api/objects/finance/AccountWallet.php';
require_once '/var/www/.structure/library/account/api/objects/finance/AccountAffiliate.php';

// Information
require_once '/var/www/.structure/library/account/api/objects/information/AccountStatistics.php';
