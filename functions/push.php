<?php

/*
 * Web Push (real OS-level notifications that fire even with no ITFlow tab or
 * browser open) via minishlink/web-push, vendored under libs/vendor.
 *
 * VAPID keys are generated once, lazily, on first send, and persisted to the
 * settings table - no manual key-generation step required.
 */

require_once __DIR__ . '/../libs/vendor/autoload.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/*
 * Provision a VAPID keypair the first time it's needed and persist it to the
 * settings table. Called from load_global_settings.php (so the public key is
 * always available to hand to the frontend for subscribing) and defensively
 * from sendPushNotification() for any code path that doesn't load settings
 * the normal way.
 */
function ensureVapidKeys() {
    global $mysqli, $config_vapid_public_key, $config_vapid_private_key, $config_vapid_subject, $config_base_url;

    if (!empty($config_vapid_public_key) && !empty($config_vapid_private_key)) {
        return;
    }

    $keys = VAPID::createVapidKeys();
    $config_vapid_public_key = $keys['publicKey'];
    $config_vapid_private_key = $keys['privateKey'];
    $config_vapid_subject = "https://$config_base_url";

    mysqli_query($mysqli, "UPDATE settings SET
        config_vapid_public_key = '" . escapeSql($config_vapid_public_key) . "',
        config_vapid_private_key = '" . escapeSql($config_vapid_private_key) . "',
        config_vapid_subject = '" . escapeSql($config_vapid_subject) . "'
        WHERE company_id = 1");
}

/*
 * Keep only the newest few subscriptions per user and browser. Firefox on
 * Android hands out a fresh endpoint on every re-subscribe and the old ones
 * keep accepting pushes, so without this the table grows without bound and
 * every notification pays one HTTP round trip per dead endpoint. A few are
 * kept so two devices sharing a user agent string don't evict each other.
 */
function prunePushSubscriptions($user_id, $user_agent) {
    global $mysqli;

    $user_id = intval($user_id);
    $user_agent = escapeSql($user_agent);

    $sql = mysqli_query($mysqli, "SELECT push_subscription_id FROM push_subscriptions
        WHERE push_subscription_user_id = $user_id AND push_subscription_user_agent <=> '$user_agent'
        ORDER BY push_subscription_id DESC LIMIT 3, 1000");

    while ($sql && $row = mysqli_fetch_assoc($sql)) {
        mysqli_query($mysqli, "DELETE FROM push_subscriptions WHERE push_subscription_id = " . intval($row['push_subscription_id']));
    }
}

/*
 * Queue a push to every device the given user has subscribed on without
 * making the caller wait. Delivery is one HTTP request per subscription, so
 * it runs in a detached CLI process (cron/push_send.php); if that process
 * can't be started (exec disabled, no CLI binary) it falls back to sending
 * inline. Silently does nothing if the user has no subscriptions.
 */
function sendPushNotification($user_id, $type, $message, $action = null) {
    global $mysqli;

    $user_id = intval($user_id);
    if (!$user_id) {
        return;
    }

    $sql = mysqli_query($mysqli, "SELECT 1 FROM push_subscriptions WHERE push_subscription_user_id = $user_id LIMIT 1");
    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }

    $php = PHP_BINDIR . '/php';
    $worker = realpath(__DIR__ . '/../cron/push_send.php');
    $can_spawn = php_sapi_name() !== 'cli'
        && function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)
        && is_executable($php)
        && $worker;

    if ($can_spawn) {
        $payload = base64_encode(json_encode([$type, $message, $action]));
        exec(escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' ' . $user_id . ' ' . escapeshellarg($payload) . ' > /dev/null 2>&1 &', $out, $code);
        if ($code === 0) {
            return;
        }
    }

    sendPushNotificationNow($user_id, $type, $message, $action);
}

/*
 * Push a real OS notification to every device the given user has subscribed
 * on, waiting for each push service to answer. Silently does nothing if the
 * user has no subscriptions, or if $user_id is empty/zero.
 */
function sendPushNotificationNow($user_id, $type, $message, $action = null) {
    global $mysqli, $config_vapid_public_key, $config_vapid_private_key, $config_vapid_subject, $config_base_url;

    $user_id = intval($user_id);
    if (!$user_id) {
        return;
    }

    $sql = mysqli_query($mysqli, "SELECT push_subscription_endpoint, push_subscription_p256dh, push_subscription_auth
        FROM push_subscriptions WHERE push_subscription_user_id = $user_id");

    if (!$sql || !mysqli_num_rows($sql)) {
        return;
    }

    ensureVapidKeys();

    // Existing notification_action values are inconsistently '/agent/x.php',
    // 'agent/x.php', or bare 'x.php' - all of which live under /agent/
    $action = (string) $action;
    if ($action === '' || $action === '#') {
        $url = "https://$config_base_url/agent/notifications.php";
    } elseif (str_starts_with($action, 'http')) {
        $url = $action;
    } elseif (str_starts_with($action, '/')) {
        $url = "https://$config_base_url$action";
    } elseif (str_starts_with($action, 'agent/')) {
        $url = "https://$config_base_url/$action";
    } else {
        $url = "https://$config_base_url/agent/$action";
    }

    $webPush = new WebPush([
        'VAPID' => [
            'subject' => $config_vapid_subject ?: "https://$config_base_url",
            'publicKey' => $config_vapid_public_key,
            'privateKey' => $config_vapid_private_key,
        ],
    ]);

    $payload = json_encode([
        'title' => $type,
        'body' => strip_tags($message),
        'url' => $url,
    ]);

    while ($row = mysqli_fetch_assoc($sql)) {
        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $row['push_subscription_endpoint'],
                'publicKey' => $row['push_subscription_p256dh'],
                'authToken' => $row['push_subscription_auth'],
            ]),
            $payload
        );
    }

    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
            $endpoint = escapeSql($report->getRequest()->getUri()->__toString());
            mysqli_query($mysqli, "DELETE FROM push_subscriptions WHERE push_subscription_endpoint = '$endpoint'");
        }
    }
}
