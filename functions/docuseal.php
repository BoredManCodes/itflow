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

    if (!empty($metadata)) {
        $data['metadata'] = $metadata;
    }

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
