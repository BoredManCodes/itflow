<?php
// Set working directory to the directory this script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";

/*
 * ITFlow - Web push worker
 *
 * Started in the background by sendPushNotification() so a page view never
 * waits on the push services:
 *
 *   php cron/push_send.php <user_id> <base64(json([type, message, action]))>
 */

$user_id = intval($argv[1] ?? 0);
$payload = json_decode(base64_decode($argv[2] ?? ''), true);

if (!$user_id || !is_array($payload) || count($payload) !== 3) {
    exit(1);
}

[$type, $message, $action] = $payload;

sendPushNotificationNow($user_id, $type, $message, $action);
