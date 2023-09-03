<?php

// Base
require_once '/var/www/.structure/library/account/api/connect.php';
require_once '/var/www/.structure/library/account/api/variables.php';

// Schedulers
require_once '/var/www/.structure/library/account/api/schedulers/refreshTransactions.php';

// Abstract
require_once '/var/www/.structure/library/account/api/objects/abstract/MinecraftPlatform.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/MethodReply.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/ParameterVerification.php';
require_once '/var/www/.structure/library/account/api/objects/abstract/AccountPatreon.php';

// Account
require_once '/var/www/.structure/library/account/api/objects/account/AccountRegistry.php';
require_once '/var/www/.structure/library/account/api/objects/account/AcceptedAccount.php';
require_once '/var/www/.structure/library/account/api/objects/account/Account.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountCooldowns.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountHistory.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountSettings.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountTransactions.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountActions.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountObjectives.php';
require_once '/var/www/.structure/library/account/api/objects/account/AccountIdentification.php';

// Session
require_once '/var/www/.structure/library/account/api/objects/session/WebsiteSession.php';
require_once '/var/www/.structure/library/account/api/objects/session/TwoFactorAuthentication.php';

// Product
require_once '/var/www/.structure/library/account/api/objects/product/AccountDownloads.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountPurchases.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountReviews.php';
require_once '/var/www/.structure/library/account/api/objects/product/ProductGiveaway.php';
require_once '/var/www/.structure/library/account/api/objects/product/RegisteredBuyers.php';
require_once '/var/www/.structure/library/account/api/objects/product/ProductDownload.php';
require_once '/var/www/.structure/library/account/api/objects/product/ProductOffer.php';
require_once '/var/www/.structure/library/account/api/objects/product/WebsiteProduct.php';
require_once '/var/www/.structure/library/account/api/objects/product/PaymentProcessor.php';
require_once '/var/www/.structure/library/account/api/objects/product/ProductCoupon.php';
require_once '/var/www/.structure/library/account/api/objects/product/AccountAffiliate.php';

// Management
require_once '/var/www/.structure/library/account/api/objects/management/AccountModerations.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountPermissions.php';
require_once '/var/www/.structure/library/account/api/objects/management/WebsiteFunctionality.php';
require_once '/var/www/.structure/library/account/api/objects/management/WebsiteModeration.php';
require_once '/var/www/.structure/library/account/api/objects/management/AccountRole.php';

// Credential
require_once '/var/www/.structure/library/account/api/objects/credential/AccountAccounts.php';
require_once '/var/www/.structure/library/account/api/objects/credential/AccountPassword.php';
require_once '/var/www/.structure/library/account/api/objects/credential/AccountFiles.php';

// Communication
require_once '/var/www/.structure/library/account/api/objects/communication/AccountEmail.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountNotifications.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountPhoneNumber.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountTeam.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountCommunication.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountCorrelation.php';
require_once '/var/www/.structure/library/account/api/objects/communication/AccountCooperation.php';