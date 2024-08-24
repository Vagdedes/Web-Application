<?php

// External
require_once '/var/www/.structure/library/base/minecraft.php';
require_once '/var/www/.structure/library/base/objects/MethodReply.php';
require_once '/var/www/.structure/library/base/objects/ParameterVerification.php';

// Base
require_once '/var/www/.structure/library/account/api/connect.php';
require_once '/var/www/.structure/library/account/api/variables.php';

// Account
require_once '/var/www/.structure/library/account/api/objects/account/Account.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountRegistry.php';

// Abstract
require_once '/var/www/.structure/library/account/api/objects/abstract/AccountPatreon.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/AccountObjectives.php';

// Account
require_once '/var/www/.structure/library/account/api/objects/account/AccountHistory.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountSettings.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountActions.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountIdentification.php';

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

// Communication
require_once '/var/www/.structure/library/account/api/objects/communication/AccountEmail.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountNotifications.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountPhoneNumber.php';

// Correlation
require_once '/var/www/.structure/library/account/api/objects/correlation/AcceptedAccount.php';
require_once '/var/www/.structure/library/account/api/objects/correlation/AccountAccounts.php';

// Finance
require_once '/var/www/.structure/library/account/api/objects/finance/AccountTransactions.php';

// Information
require_once '/var/www/.structure/library/account/api/objects/information/AccountInstructions.php';
require_once '/var/www/.structure/library/account/api/objects/information/AccountStatistics.php';

// Security
require_once '/var/www/.structure/library/account/api/objects/security/AccountSession.php';
require_once '/var/www/.structure/library/account/api/objects/security/TwoFactorAuthentication.php';
require_once '/var/www/.structure/library/account/api/objects/security/AccountPassword.php';

// Finance
require_once '/var/www/.structure/library/account/api/objects/finance/PaymentProcessor.php';
