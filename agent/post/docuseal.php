<?php

/*
 * ITFlow - GET/POST request handler for DocuSeal e-signature onboarding forms
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['send_onboarding_form'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $client_id = intval($_POST['client_id']);
    $template_key = trim($_POST['docuseal_template']);
    $client_email = filter_var(trim($_POST['client_email']), FILTER_VALIDATE_EMAIL);
    $prefill_entity_name = trim($_POST['prefill_entity_name']);
    $prefill_abn = trim($_POST['prefill_abn']);
    $prefill_address = trim($_POST['prefill_address']);

    if (!docusealConfigured()) {
        flashAlert("DocuSeal is not configured on this install.", 'error');
        redirect();
    }

    if (!$client_email) {
        flashAlert("Enter a valid email address to send to.", 'error');
        redirect();
    }

    if (!array_key_exists($template_key, DOCUSEAL_TEMPLATES)) {
        flashAlert("Unknown onboarding form.", 'error');
        redirect();
    }

    $template_id = DOCUSEAL_TEMPLATES[$template_key];

    $submitters = [];

    if (defined('DOCUSEAL_PROVIDER_EMAIL')) {
        $submitters[] = [
            'role' => 'Provider',
            'email' => DOCUSEAL_PROVIDER_EMAIL,
        ];
    }

    $submitters[] = [
        'role' => 'Client',
        'email' => $client_email,
        'values' => [
            'Agreement Date' => date('Y-m-d'),
            'Client Legal Entity Name' => $prefill_entity_name,
            'Client ABN' => $prefill_abn,
            'Client Address' => $prefill_address,
        ],
    ];

    $result = docusealCreateSubmission($template_id, $submitters, ['itflow_client_id' => $client_id]);

    if ($result['ok']) {
        logAudit("Client", "DocuSeal", "$session_name sent onboarding form ($template_key) to $client_email", $client_id);

        $provider_link = '';
        foreach ($result['submission'] as $submitter) {
            if (($submitter['role'] ?? '') === 'Provider' && !empty($submitter['embed_src'])) {
                $provider_link = $submitter['embed_src'];
                break;
            }
        }

        // A toast that vanishes in a few seconds is not a reliable way to
        // remember "go fill in your side of this contract" - leave an actual
        // ticket on the sender's own queue instead, the way every other
        // outstanding task in this app already works.
        if ($provider_link) {
            docusealCreateReminderTicket($session_user_id, $client_id, $template_key, $provider_link);
        }

        flashAlert("Onboarding form sent to <strong>$client_email</strong>. A reminder ticket was created for your part.");
    } else {
        flashAlert("Could not send onboarding form: " . escapeHtml($result['error']), 'error');
    }

    redirect();
}
