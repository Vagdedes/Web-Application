<?php

// External
require_once '/var/www/.structure/library/base/minecraft.php';
require_once '/var/www/.structure/library/base/objects/AbstractMethodReply.php';
require_once '/var/www/.structure/library/base/objects/MethodReply.php';
require_once '/var/www/.structure/library/base/objects/ParameterVerification.php';

// Base
require_once '/var/www/.structure/library/account/api/connect.php';
require_once '/var/www/.structure/library/account/api/variables.php';

// Account
require_once '/var/www/.structure/library/account/api/objects/account/Account.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountRegistry.php';

// Product
require_once '/var/www/.structure/library/account/api/objects/product/AccountProductDownloads.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountPurchases.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountGiveaway.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountProduct.php';
require_once '/var/www/.structure/library/account/api/objects/product/ProductCoupon.php';

// Management
require_once '/var/www/.structure/library/account/api/objects/management/AccountModerations.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountPermissions.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountFunctionality.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountRole.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountCooldowns.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountSettings.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountActions.php';

// Communication
require_once '/var/www/.structure/library/account/api/objects/communication/AccountEmail.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountNotifications.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountPhoneNumber.php';

// Correlation
require_once '/var/www/.structure/library/account/api/objects/correlation/AcceptedAccount.php';
require_once '/var/www/.structure/library/account/api/objects/correlation/AccountAccounts.php';
require_once '/var/www/.structure/library/account/api/objects/correlation/AccountIdentification.php';
require_once '/var/www/.structure/library/account/api/objects/correlation/AccountTeam.php';

// Finance
require_once '/var/www/.structure/library/account/api/objects/finance/AccountTransactions.php';
require_once '/var/www/.structure/library/account/api/objects/finance/PaymentProcessor.php';
require_once '/var/www/.structure/library/account/api/objects/finance/AccountPatreon.php';
require_once '/var/www/.structure/library/account/api/objects/finance/AccountEmbeddings.php';

// Information
require_once '/var/www/.structure/library/account/api/objects/information/AccountInstructions.php';
require_once '/var/www/.structure/library/account/api/objects/information/AccountStatistics.php';
require_once '/var/www/.structure/library/account/api/objects/information/AccountHistory.php';
require_once '/var/www/.structure/library/account/api/objects/information/AccountTranslation.php';

// Security
require_once '/var/www/.structure/library/account/api/objects/security/AccountSession.php';
require_once '/var/www/.structure/library/account/api/objects/security/TwoFactorAuthentication.php';
require_once '/var/www/.structure/library/account/api/objects/security/AccountPassword.php';
