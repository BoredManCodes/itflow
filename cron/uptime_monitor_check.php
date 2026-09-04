<?php

/*
 * CRON - Uptime Monitor Check
 *
 * Polls the UptimeRobot API for the monitors listed in the uptime_monitors table and
 * turns a down/up transition into a ticket - one ticket per outage, opened when a
 * tracked monitor goes down and auto-resolved (not replaced by a second ticket) when
 * it comes back up. Replaces relying on UptimeRobot's alert emails, which have no
 * stable link between a "down" and its matching "up" message for the email parser to
 * close the loop on.
 */

$script_start_time = microtime(true);

// Set working directory to the directory this cron script lives at.
chdir(dirname(__FILE__));

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Prevent overlapping runs of this script
$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";

// Set Timezone
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";

// Get settings for the "default" company
require_once "../includes/load_global_settings.php";

// Check cron is enabled
if ($config_enable_cron == 0) {
    logApp("Cron-Uptime-Monitor-Check", "error", "Cron Uptime Monitor Check unable to run - cron not enabled in admin settings.");
    cronJobStop("Cron: is not enabled -- Quitting..");
}

$api_key = trim((string) $config_uptimerobot_api_key);

if ($api_key === '') {
    // Not configured - nothing to poll
    cronJobStop();
}

$monitors_sql = mysqli_query($mysqli, "SELECT * FROM uptime_monitors");
if (!$monitors_sql || !mysqli_num_rows($monitors_sql)) {
    cronJobStop();
}

$tracked_monitors = [];
while ($tracked_row = mysqli_fetch_assoc($monitors_sql)) {
    $tracked_monitors[] = $tracked_row;
}

$response = httpFormPost('https://api.uptimerobot.com/v2/getMonitors', [
    'api_key' => $api_key,
    'format' => 'json',
]);

if (!$response['ok']) {
    logApp("Cron-Uptime-Monitor-Check", "error", "UptimeRobot API request failed: " . ($response['err'] ?: "HTTP {$response['code']}"));
    cronJobStop('', 1);
}

$payload = json_decode((string) $response['body'], true);

if (!is_array($payload) || ($payload['stat'] ?? '') !== 'ok' || !isset($payload['monitors'])) {
    $error_message = $payload['error']['message'] ?? 'unrecognised response';
    logApp("Cron-Uptime-Monitor-Check", "error", "UptimeRobot API returned an error: $error_message");
    cronJobStop('', 1);
}

// Index the API's monitors by friendly name (case-insensitive) - that name is what ties
// an uptime_monitors row to a monitor on UptimeRobot's side; the numeric ID is only
// learned and cached here for reference
$monitors_by_name = [];
foreach ($payload['monitors'] as $api_monitor) {
    $monitors_by_name[strtolower(trim((string) $api_monitor['friendly_name']))] = $api_monitor;
}

/** ------------------------------------------------------------------
 * Ticket helpers
 * ------------------------------------------------------------------ */

// UptimeRobot monitor status codes: 0 paused, 1 not checked yet, 2 up, 8 seems down, 9 down.
// Only a confirmed 9 opens a ticket - "seems down" (8) is UptimeRobot's own retry-in-progress
// state and resolves itself most of the time.
function createUptimeDownTicket($tracked_monitor, $api_monitor) {
    global $mysqli, $config_ticket_prefix, $config_ticket_from_name, $config_ticket_from_email,
        $config_ticket_client_general_notifications, $config_ticket_default_billable, $config_base_url;

    $client_id = intval($tracked_monitor['uptime_monitor_client_id']);
    $contact_id = intval($tracked_monitor['uptime_monitor_contact_id']);
    $monitor_name = escapeSql($api_monitor['friendly_name']);
    $monitor_url = escapeSql($api_monitor['url'] ?? '');

    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");
    $ticket_number = mysqli_insert_id($mysqli);

    $subject = "$monitor_name is down";
    $details = "UptimeRobot reports <b>$monitor_name</b>"
        . ($monitor_url !== '' ? " ($monitor_url)" : "")
        . " is down as of " . date('Y-m-d H:i:s T') . ".";

    $url_key = randomString(32);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Uptime Monitor', ticket_subject = '$subject', ticket_details = '$details', ticket_priority = 'Urgent', ticket_status = 1, ticket_billable = $config_ticket_default_billable, ticket_created_by = 0, ticket_contact_id = $contact_id, ticket_url_key = '$url_key', ticket_client_id = $client_id");
    $ticket_id = mysqli_insert_id($mysqli);

    applyTicketSla($ticket_id);
    applyTicketAutoAssign($ticket_id);
    applyTicketRules($ticket_id);

    logAudit("Ticket", "Create", "Uptime monitor check: $monitor_name is down, created ticket $config_ticket_prefix$ticket_number", $client_id, $ticket_id);

    $client_uri = $client_id ? "&client_id=$client_id" : '';
    appNotify("Ticket", "New ticket: $monitor_name is down", "/agent/ticket.php?ticket_id=$ticket_id$client_uri", $client_id, $ticket_id);

    // Notify the mapped contact, the same way any other new ticket with a contact does
    if ($config_ticket_client_general_notifications == 1) {
        $contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email FROM contacts WHERE contact_id = $contact_id LIMIT 1"));
        $contact_name = escapeSql($contact_row['contact_name'] ?? '');
        $contact_email = escapeSql($contact_row['contact_email'] ?? '');

        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $company_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1"));
            $company_name = escapeSql($company_row['company_name']);
            $company_phone = escapeSql(formatPhoneNumber($company_row['company_phone'], $company_row['company_phone_country_code']));

            $sla_notice = getTicketSlaEmailNotice($ticket_id, $company_phone);
            $rendered = renderEmailTemplate('ticket_created', [
                'contact_name' => $contact_name,
                'ticket_subject' => $subject,
                'ticket_details' => $details,
                'ticket_prefix' => $config_ticket_prefix,
                'ticket_number' => $ticket_number,
                'ticket_status' => 'Open',
                'ticket_url' => "https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key",
                'sla_notice' => $sla_notice,
                'company_name' => $company_name,
                'company_phone' => $company_phone,
                'from_email' => $config_ticket_from_email,
            ]);

            addToMailQueue([[
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
            ]]);
        }
    }

    triggerCustomAction('ticket_create', $ticket_id);

    return $ticket_id;
}

function resolveUptimeTicket($ticket_id, $api_monitor) {
    global $mysqli, $config_base_url, $config_ticket_from_name, $config_ticket_from_email, $config_ticket_client_general_notifications;

    $ticket_id = intval($ticket_id);
    $monitor_name = escapeSql($api_monitor['friendly_name']);

    $ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status, ticket_client_id, ticket_prefix, ticket_number, ticket_subject, ticket_url_key, contact_name, contact_email
        FROM tickets LEFT JOIN contacts ON ticket_contact_id = contact_id WHERE ticket_id = $ticket_id LIMIT 1"));

    // The ticket this monitor was tracking is gone (deleted) - nothing to resolve
    if (!$ticket) {
        return;
    }

    $client_id = intval($ticket['ticket_client_id']);

    // Already resolved or closed by a human in the meantime - leave it alone
    if (in_array(intval($ticket['ticket_status']), [4, 5], true)) {
        return;
    }

    mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
    syncTicketSlaClock($ticket_id);
    setTicketResolutionSlaMet($ticket_id);

    mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Monitor back online - auto-resolved by the uptime monitor check.', ticket_reply_type = 'Internal', ticket_reply_time_worked = '00:00:00', ticket_reply_by = 0, ticket_reply_ticket_id = $ticket_id");

    logTicketHistory($ticket_id, "$monitor_name back up, auto-resolved by the uptime monitor check");
    logAudit("Ticket", "Resolve", "Uptime monitor check: $monitor_name back up, resolved ticket ID $ticket_id", $client_id, $ticket_id);
    triggerCustomAction('ticket_resolve', $ticket_id);

    if ($config_ticket_client_general_notifications == 1) {
        $contact_name = escapeSql($ticket['contact_name'] ?? '');
        $contact_email = escapeSql($ticket['contact_email'] ?? '');

        if (filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $ticket_prefix = escapeSql($ticket['ticket_prefix']);
            $ticket_number = intval($ticket['ticket_number']);
            $ticket_subject = escapeSql($ticket['ticket_subject']);
            $url_key = escapeSql($ticket['ticket_url_key']);

            $company_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_phone, company_phone_country_code FROM companies WHERE company_id = 1"));
            $company_name = escapeSql($company_row['company_name']);
            $company_phone = escapeSql(formatPhoneNumber($company_row['company_phone'], $company_row['company_phone_country_code']));

            $rendered = renderEmailTemplate('ticket_resolved_pending_closure', [
                'contact_name' => $contact_name,
                'ticket_subject' => $ticket_subject,
                'ticket_reply' => "$monitor_name is back online.",
                'ticket_reopen_url' => "https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key",
                'ticket_prefix' => $ticket_prefix,
                'ticket_number' => $ticket_number,
                'ticket_status' => 'Resolved',
                'ticket_url' => "https://$config_base_url/guest/guest_view_ticket.php?ticket_id=$ticket_id&url_key=$url_key",
                'company_name' => $company_name,
                'company_phone' => $company_phone,
                'from_email' => $config_ticket_from_email,
            ]);

            addToMailQueue([[
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
            ]]);
        }
    }
}

/** ------------------------------------------------------------------
 * Walk the tracked monitors, act on down/up transitions only
 * ------------------------------------------------------------------ */

foreach ($tracked_monitors as $tracked) {
    $monitor_row_id = intval($tracked['uptime_monitor_id']);
    $name_key = strtolower(trim($tracked['uptime_monitor_name']));

    if (!isset($monitors_by_name[$name_key])) {
        logApp("Cron-Uptime-Monitor-Check", "warning", "No UptimeRobot monitor named '{$tracked['uptime_monitor_name']}' found on this account.");
        continue;
    }

    $api_monitor = $monitors_by_name[$name_key];
    $new_status = intval($api_monitor['status']);
    $old_status = $tracked['uptime_monitor_last_status'] !== null ? intval($tracked['uptime_monitor_last_status']) : null;
    $provider_id = intval($api_monitor['id']);
    $open_ticket_id = intval($tracked['uptime_monitor_open_ticket_id']);

    if ($new_status === 9 && $old_status !== 9) {

        // Don't open a second ticket if the last outage's ticket is still open for some reason
        $already_open = false;
        if ($open_ticket_id) {
            $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status FROM tickets WHERE ticket_id = $open_ticket_id LIMIT 1"));
            $already_open = $existing && !in_array(intval($existing['ticket_status']), [4, 5], true);
        }

        if (!$already_open) {
            $new_ticket_id = createUptimeDownTicket($tracked, $api_monitor);
            mysqli_query($mysqli, "UPDATE uptime_monitors SET uptime_monitor_open_ticket_id = $new_ticket_id WHERE uptime_monitor_id = $monitor_row_id");
        }

    } elseif ($new_status === 2 && $old_status === 9) {

        if ($open_ticket_id) {
            resolveUptimeTicket($open_ticket_id, $api_monitor);
        }
        mysqli_query($mysqli, "UPDATE uptime_monitors SET uptime_monitor_open_ticket_id = NULL WHERE uptime_monitor_id = $monitor_row_id");

    }

    mysqli_query($mysqli, "UPDATE uptime_monitors SET uptime_monitor_last_status = $new_status, uptime_monitor_provider_id = $provider_id WHERE uptime_monitor_id = $monitor_row_id");
}

$execution_time = number_format(microtime(true) - $script_start_time, 2);
logApp("Cron-Uptime-Monitor-Check", "info", "Cron Uptime Monitor Check executed in $execution_time seconds.");

echo "Uptime monitor check complete.\n";
