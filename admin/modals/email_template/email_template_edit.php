<?php

require_once '../../includes/modal_header.php';

$email_template_key = strval($_GET['key']);

$defaults = emailTemplateDefaults();

if (!isset($defaults[$email_template_key])) {
    echo "<div class='modal-body'><p class='text-danger'>Unknown template.</p></div>";
    require_once '../../../includes/modal_footer.php';
    exit();
}

$default = $defaults[$email_template_key];

$sql = mysqli_query($mysqli, "SELECT email_template_subject, email_template_body FROM email_templates WHERE email_template_key = '" . escapeSql($email_template_key) . "' LIMIT 1");
$row = mysqli_fetch_assoc($sql);

$email_template_name = escapeHtml($default['name']);
$email_template_subject = escapeHtml($row['email_template_subject'] ?? $default['subject']);
$email_template_body = escapeHtml($row['email_template_body'] ?? $default['body']);
$email_template_tokens = $default['tokens'];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-envelope-open-text mr-2"></i><?= $email_template_name ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="email_template_key" value="<?= escapeHtml($email_template_key) ?>">

    <div class="modal-body">

        <?php if (!empty($email_template_tokens)) { ?>
            <p class="text-muted">Available placeholders - drop any of these into the subject or body, they'll be swapped in when the email is sent:</p>
            <p>
                <?php foreach (array_map('trim', explode(',', $email_template_tokens)) as $token) { ?>
                    <code class="mr-1">{<?= escapeHtml($token) ?>}</code>
                <?php } ?>
            </p>
            <hr>
        <?php } ?>

        <div class="form-group">
            <label>Subject <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="subject" maxlength="255" value="<?= $email_template_subject ?>" required>
        </div>

        <div class="form-group">
            <label>Body <strong class="text-danger">*</strong></label>
            <textarea class="form-control" name="body" rows="12" required><?= $email_template_body ?></textarea>
            <small class="text-muted">HTML is allowed - this is sent as the email body as-is.</small>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_email_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="submit" name="send_test_email_template" class="btn btn-secondary" title="Sends this subject/body (including unsaved edits) to your own email, with sample values filled in"><i class="fa fa-paper-plane mr-2"></i>Send Test</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
