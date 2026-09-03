<?php

/*
 * DocuSeal integration - self-hosted e-signature service.
 *
 * DOCUSEAL_BASE_URL, DOCUSEAL_API_KEY, and DOCUSEAL_TEMPLATES (an array mapping
 * a short key like 'nda' to that install's DocuSeal template_id) are defined in
 * config.php, same as the DB credentials - they are install-specific and never
 * committed. Every function here no-ops safely when they are not set, so this
 * runs fine on installs that have not configured DocuSeal.
 */

function docusealConfigured() {
    return defined('DOCUSEAL_BASE_URL') && defined('DOCUSEAL_API_KEY') && defined('DOCUSEAL_TEMPLATES');
}

/*
 * Creates a signature request from an existing DocuSeal template.
 *
 * $submitters is DocuSeal's own submitters array shape, e.g.:
 *   [['role' => 'Client', 'email' => 'x@example.com', 'values' => ['Field Name' => 'value']]]
 */
function docusealCreateSubmission($template_id, $submitters, $metadata = []) {
    if (!docusealConfigured()) {
        return ['ok' => false, 'error' => 'DocuSeal is not configured'];
    }

    // DocuSeal has no submission-level metadata field - only submitters carry
    // one (confirmed in Api::SubmissionsController's permitted params; a
    // top-level 'metadata' key is silently dropped by strong parameters and
    // never reaches the webhook payload). Stamp it onto every submitter instead,
    // so the webhook can read itflow_client_id off whichever one it gets first.
    if (!empty($metadata)) {
        $submitters = array_map(function ($submitter) use ($metadata) {
            $submitter['metadata'] = array_merge($submitter['metadata'] ?? [], $metadata);
            return $submitter;
        }, $submitters);
    }

    $data = [
        'template_id' => intval($template_id),
        'submitters' => $submitters,
        // DocuSeal's default 'preserved' order sends each submitter's invite only
        // after the one before them completes. Provider is listed first with
        // send_email=false (they fill in via the dashboard, not an emailed link),
        // so 'preserved' order would leave the Client submitter waiting forever
        // on a Provider who was never notified to go first. 'random' lets every
        // submitter's own send_email flag govern them independently.
        'order' => 'random',
    ];

    $ch = curl_init(rtrim(DOCUSEAL_BASE_URL, '/') . '/api/submissions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Auth-Token: ' . DOCUSEAL_API_KEY,
    ]);

    $response = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    $transport_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        logApp('DocuSeal', 'error', "Could not reach DocuSeal: $transport_error");
        return ['ok' => false, 'error' => 'Could not reach DocuSeal.'];
    }

    $body = json_decode($response, true);

    if ($status < 200 || $status >= 300) {
        $message = $body['error'] ?? $body['message'] ?? "HTTP $status";
        logApp('DocuSeal', 'error', "Submission create failed: $message");
        return ['ok' => false, 'error' => $message];
    }

    return ['ok' => true, 'submission' => $body];
}

/*
 * Leaves an internal ticket on the sending user's own queue linking to their
 * outstanding DocuSeal signing step, assigned straight to them so it shows up
 * wherever they already track open work - a flash toast disappears in a few
 * seconds and is not a real reminder.
 */
function docusealCreateReminderTicket($user_id, $client_id, $template_key, $provider_link) {
    global $mysqli, $config_ticket_prefix;

    $client_id = intval($client_id);
    $user_id = intval($user_id);

    $sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id LIMIT 1");
    $client_name = escapeHtml(mysqli_fetch_assoc($sql)['client_name'] ?? 'client');

    $template_labels = [
        'nda' => 'Confidentiality Agreement (NDA)',
        'services' => 'IT Services Agreement',
    ];
    $template_label = $template_labels[$template_key] ?? $template_key;

    $subject = escapeSql("Sign your part of the $template_label - $client_name");
    $details = mysqli_real_escape_string(
        $mysqli,
        "<p>You sent the <strong>" . htmlspecialchars($template_label, ENT_QUOTES) . "</strong> to <strong>" . htmlspecialchars($client_name, ENT_QUOTES) . "</strong> for signing.</p>"
        . "<p>Your part (Provider fields and signature) is still outstanding:</p>"
        . "<p><a href=\"" . htmlspecialchars($provider_link, ENT_QUOTES) . "\" target=\"_blank\">" . htmlspecialchars($provider_link, ENT_QUOTES) . "</a></p>"
    );

    $prefix = escapeSql($config_ticket_prefix ?? '');

    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");
    $ticket_number = mysqli_insert_id($mysqli);

    $url_key = randomString(32);

    mysqli_query($mysqli, "INSERT INTO tickets SET
        ticket_prefix = '$prefix',
        ticket_number = $ticket_number,
        ticket_source = 'DocuSeal',
        ticket_category = 0,
        ticket_subject = '$subject',
        ticket_details = '$details',
        ticket_priority = 'Medium',
        ticket_billable = 0,
        ticket_status = 2,
        ticket_created_by = $user_id,
        ticket_assigned_to = $user_id,
        ticket_contact_id = 0,
        ticket_url_key = '$url_key',
        ticket_due_at = NULL,
        ticket_client_id = $client_id,
        ticket_invoice_id = 0,
        ticket_project_id = 0
    ");

    $ticket_id = mysqli_insert_id($mysqli);
    applyTicketSla($ticket_id);
    applyTicketRules($ticket_id);

    return $ticket_id;
}
