<?php

// Email Templates

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_email_template'])) {

    validateCSRFToken();

    $key = strval($_POST['email_template_key']);
    $defaults = emailTemplateDefaults();

    if (!isset($defaults[$key])) {
        flashAlert("Unknown email template", 'error');
        redirect("email_templates.php");
    }

    $name = escapeSql($defaults[$key]['name']);
    $key_esc = escapeSql($key);
    // Not escapeSql() for subject/body - it strip_tags()'s the input, which would
    // strip the HTML these templates are made of.
    $subject = mysqli_real_escape_string($mysqli, trim($_POST['subject']));
    $body = mysqli_real_escape_string($mysqli, $_POST['body']);
    $tokens = mysqli_real_escape_string($mysqli, $defaults[$key]['tokens']);

    if ($subject === '' || trim($_POST['body']) === '') {
        flashAlert("Subject and body cannot be empty", 'error');
        redirect("email_templates.php");
    }

    mysqli_query($mysqli, "INSERT INTO email_templates SET email_template_key = '$key_esc', email_template_name = '$name',
        email_template_subject = '$subject', email_template_body = '$body', email_template_tokens = '$tokens'
        ON DUPLICATE KEY UPDATE email_template_subject = '$subject', email_template_body = '$body'");

    logAudit("Email Template", "Edit", "$session_name edited email template $key");

    flashAlert("Email template <strong>" . escapeHtml($defaults[$key]['name']) . "</strong> saved");

    redirect("email_templates.php");

}

if (isset($_POST['send_test_email_template'])) {

    validateCSRFToken();

    $key = strval($_POST['email_template_key']);
    $defaults = emailTemplateDefaults();

    if (!isset($defaults[$key])) {
        flashAlert("Unknown email template", 'error');
        redirect("email_templates.php");
    }

    if (empty($session_email) || !filter_var($session_email, FILTER_VALIDATE_EMAIL)) {
        flashAlert("Your user account has no valid email address to send a test to", 'error');
        redirect("email_templates.php");
    }

    // Test sends whatever is currently in the form - including unsaved edits -
    // so a template can be previewed before it's saved.
    $subject = trim($_POST['subject']);
    $body = $_POST['body'];

    $sample_tokens = [];
    foreach (array_map('trim', explode(',', $defaults[$key]['tokens'])) as $token) {
        if ($token === '') {
            continue;
        }
        $sample_tokens['{' . $token . '}'] = '[' . ucwords(str_replace('_', ' ', $token)) . ']';
    }

    $data = [
        [
            'from' => $config_ticket_from_email,
            'from_name' => $config_ticket_from_name,
            'recipient' => $session_email,
            'recipient_name' => $session_name,
            'subject' => '[TEST] ' . strtr($subject, $sample_tokens),
            'body' => wrapEmailHtml(strtr($body, $sample_tokens)),
        ]
    ];

    $mail = addToMailQueue($data);

    if ($mail === true) {
        flashAlert("Test email queued - it'll land in <strong>" . escapeHtml($session_email) . "</strong> shortly");
    } else {
        flashAlert("Failed to queue test email", 'error');
    }

    redirect("email_templates.php");

}

if (isset($_GET['reset_email_template'])) {

    validateCSRFToken();

    $key = strval($_GET['reset_email_template']);
    $key_esc = escapeSql($key);

    mysqli_query($mysqli, "DELETE FROM email_templates WHERE email_template_key = '$key_esc'");

    logAudit("Email Template", "Edit", "$session_name reset email template $key to its default");

    flashAlert("Email template reset to default", 'error');

    redirect("email_templates.php");

}
