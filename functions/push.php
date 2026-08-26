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
 * Push a real OS notification to every device the given user has subscribed
 * on. Silently does nothing if the user has no subscriptions, or if $user_id
 * is empty/zero.
 */
function sendPushNotification($user_id, $type, $message, $action = null) {
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
