<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 2);

$client_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT client_name, client_tax_id_number FROM clients WHERE client_id = $client_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$client_name = escapeHtml($row['client_name']);
$client_tax_id_number = escapeHtml($row['client_tax_id_number']);

$sql_location = mysqli_query($mysqli, "SELECT location_address, location_city, location_state, location_zip FROM locations WHERE location_client_id = $client_id AND location_primary = 1 LIMIT 1");
$location_row = mysqli_fetch_assoc($sql_location) ?: [];
$client_address = escapeHtml(trim(implode(', ', array_filter([
    $location_row['location_address'] ?? '',
    $location_row['location_city'] ?? '',
    $location_row['location_state'] ?? '',
    $location_row['location_zip'] ?? '',
])), ', '));

$sql_contact = mysqli_query($mysqli, "SELECT contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1 AND contact_archived_at IS NULL LIMIT 1");
$contact_row = mysqli_fetch_assoc($sql_contact) ?: [];
$contact_email = escapeHtml($contact_row['contact_email'] ?? '');

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-signature me-2"></i>Send Onboarding Form: <strong><?= $client_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="modal-body bg-white">

        <?php if (empty($contact_email)) { ?>
        <div class="alert alert-warning">This client has no primary contact email on file. Add one on the Details tab before sending.</div>
        <?php } ?>

        <div class="mb-3">
            <label>Form <strong class="text-danger">*</strong></label>
            <select class="form-select" name="docuseal_template" required>
                <option value="nda">Confidentiality Agreement (NDA)</option>
                <option value="services">IT Services Agreement</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Send To <strong class="text-danger">*</strong></label>
            <input type="email" class="form-control" name="client_email" placeholder="client@example.com" value="<?= $contact_email ?>" required>
        </div>

        <div class="mb-3">
            <label>Client Legal / Entity Name</label>
            <input type="text" class="form-control" name="prefill_entity_name" value="<?= $client_name ?>">
        </div>
        <div class="mb-3">
            <label>Client ABN</label>
            <input type="text" class="form-control" name="prefill_abn" value="<?= $client_tax_id_number ?>">
        </div>
        <div class="mb-3">
            <label>Client Address</label>
            <input type="text" class="form-control" name="prefill_address" value="<?= $client_address ?>">
        </div>

        <small class="text-secondary">The client gets a signing link by email. Deal-specific terms (fees, support hours, notice periods) are filled in DocuSeal when you review the submission before it sends.</small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="send_onboarding_form" class="btn btn-primary text-bold"><i class="fa fa-paper-plane me-2"></i>Send</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
