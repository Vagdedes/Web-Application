<?php
$paypal_api_username = "";
$paypal_api_password = "";
$paypal_api_signature = "";
$paypal_api_url = "https://www.paypal.com/cgi-bin/webscr";
$paypal_api_latest_version = 220;

function access_personal_paypal_account()
{
    $paypal_credentials = get_keys_from_file("/var/www/.structure/private/paypal_credentials", 9);

    if ($paypal_credentials === null) {
        return false;
    }
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature;
    $paypal_api_username = $paypal_credentials[0];
    $paypal_api_password = $paypal_credentials[1];
    $paypal_api_signature = $paypal_credentials[2];
    return true;
}

function access_business_paypal_account()
{
    $paypal_credentials = get_keys_from_file("/var/www/.structure/private/paypal_credentials", 9);

    if ($paypal_credentials === null) {
        return false;
    }
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature;
    $paypal_api_username = $paypal_credentials[3];
    $paypal_api_password = $paypal_credentials[4];
    $paypal_api_signature = $paypal_credentials[5];
    return true;
}

function access_deactivated_personal_paypal_account()
{
    $paypal_credentials = get_keys_from_file("/var/www/.structure/private/paypal_credentials", 9);

    if ($paypal_credentials === null) {
        return false;
    }
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature;
    $paypal_api_username = $paypal_credentials[6];
    $paypal_api_password = $paypal_credentials[7];
    $paypal_api_signature = $paypal_credentials[8];
    return true;
}

function exit_paypal_account()
{
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature;
    $paypal_api_username = "";
    $paypal_api_password = "";
    $paypal_api_signature = "";
}

function get_paypal_transaction_details($transaction)
{
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature, $paypal_api_latest_version;

    $info = 'USER=' . $paypal_api_username
        . '&PWD=' . $paypal_api_password
        . '&SIGNATURE=' . $paypal_api_signature
        . '&VERSION=' . $paypal_api_latest_version
        . '&METHOD=GetTransactionDetails'
        . '&TRANSACTIONID=' . $transaction;

    $curl = curl_init('https://api-3t.paypal.com/nvp');
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $info);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_POST, 1);

    $result = curl_exec($curl);
    parse_str($result, $result);
    return $result;
}

function search_paypal_transactions($searchArguments)
{
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature, $paypal_api_latest_version;

    $info = 'USER=' . $paypal_api_username
        . '&PWD=' . $paypal_api_password
        . '&SIGNATURE=' . $paypal_api_signature
        . '&VERSION=' . $paypal_api_latest_version
        . '&METHOD=TransactionSearch'
        . '&' . $searchArguments;

    $curl = curl_init('https://api-3t.paypal.com/nvp');
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $info);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_POST, 1);

    $result = curl_exec($curl);
    parse_str($result, $result);
    return $result;
}

function refund_paypal_transaction($transactionID, $partial, $amount, $currency, $note = null)
{
    global $paypal_api_username, $paypal_api_password, $paypal_api_signature, $paypal_api_latest_version;

    $info = 'USER=' . $paypal_api_username
        . '&PWD=' . $paypal_api_password
        . '&SIGNATURE=' . $paypal_api_signature
        . '&VERSION=' . $paypal_api_latest_version
        . '&METHOD=RefundTransaction'
        . '&TRANSACTIONID=' . $transactionID
        . '&REFUNDTYPE=' . ($partial ? 'Partial' : 'Full')
        . ($partial ? '&AMT=' . $amount : '')
        . ($partial ? '&CURRENCYCODE=' . $currency : '')
        . ($note !== null ? '&NOTE=' . $note : '');

    $curl = curl_init('https://api-3t.paypal.com/nvp');
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($curl, CURLOPT_POSTFIELDS, $info);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_POST, 1);

    $result = curl_exec($curl);
    parse_str($result, $result);
    return is_array($result) && isset($result["ACK"]) && $result["ACK"] === "Success" ? true : $result;
}
