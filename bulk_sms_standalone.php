<?php
/**
 * Standalone Bulk SMS helper for use on a different website.
 * Uses Pradytec bulk SMS API (bulk.pradytec.com).
 *
 * SETUP:
 * 1. Define SMS_SURL (and optionally SMS_BURL, SMS_PURL) or set $sms_config['base_url'].
 * 2. Set your credentials in $sms_config (apikey, appname, senderid).
 * 3. Include this file and call sendSMS($to, $message).
 *
 * No database or other project files required.
 */

// --- Configuration (set these for your new site) ---
if (!defined('SMS_SURL')) {
    define('SMS_SURL', 'http://bulk.pradytec.com/api/sms/send');
}
if (!defined('SMS_BURL')) {
    define('SMS_BURL', 'http://bulk.pradytec.com/api/sms/balance');
}
if (!defined('SMS_PURL')) {
    define('SMS_PURL', 'http://bulk.pradytec.com/api/sms/payment');
}

/**
 * Return SMS API config for your site.
 * Replace this with your own logic (DB, env, config file).
 */
function get_sms_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    // Option A: from environment
    // $config = [
    //     'apikey'   => getenv('SMS_APIKEY'),
    //     'appname'  => getenv('SMS_APPNAME'),
    //     'senderid' => getenv('SMS_SENDERID'),
    // ];
    // Option B: hardcode for testing (do not use in production)
    $config = [
        'apikey'   => 'YOUR_API_KEY',
        'appname'  => 'YOUR_APP_NAME',
        'senderid' => 'YOUR_SENDER_ID',
    ];
    return $config;
}

/**
 * Send SMS via bulk API.
 *
 * @param string $to     One phone number (e.g. 254712345678) or comma-separated list.
 * @param string $mssg   Message text.
 * @return string        "Sent to X Cost Y" on success, or error message.
 */
function sendSMS($to, $mssg) {
    $config = get_sms_config();
    $data = json_encode([
        'recipients' => $to,
        'message'    => $mssg,
        'apikey'     => $config['apikey'],
        'appname'    => $config['appname'],
        'senderId'   => $config['senderid'],
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SMS_SURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Cache-Control: no-cache',
        'Content-Type: application/json; charset=utf-8',
    ]);

    $res   = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return $error;
    }
    $res = json_decode($res, true);
    return (isset($res['response']) && $res['response'] === 'success')
        ? 'Sent to ' . $res['data']['sendto'] . ' Cost ' . $res['data']['cost']
        : (isset($res['response']) ? $res['response'] : $res);
}

/**
 * Get SMS wallet balance (optional).
 *
 * @return string e.g. "KES 100" or "NULL" on error.
 */
function sms_balance() {
    $config = get_sms_config();
    $data   = json_encode([
        'apikey'  => $config['apikey'],
        'appname' => $config['appname'],
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SMS_BURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Cache-Control: no-cache',
        'Content-Type: application/json; charset=utf-8',
    ]);

    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return 'NULL';
    }
    $out = json_decode($result, true);
    return isset($out['data']['balance']) ? 'KES ' . $out['data']['balance'] : 'NULL';
}

/**
 * Request SMS wallet top-up (optional).
 *
 * @param string $phone e.g. 254712345678
 * @param string $amnt  Amount
 * @return string API response message.
 */
function paysms($phone, $amnt) {
    $config = get_sms_config();
    $data   = json_encode([
        'phone'   => $phone,
        'amount'  => $amnt,
        'apikey'  => $config['apikey'],
        'appname' => $config['appname'],
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SMS_PURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Cache-Control: no-cache',
        'Content-Type: application/json; charset=utf-8',
    ]);

    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return $error;
    }
    $out = json_decode($result, true);
    return isset($out['response']) ? $out['response'] : $result;
}
