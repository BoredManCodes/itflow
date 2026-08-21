<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$hours = max(0, min(23, intval($_GET['hours'] ?? 0)));
$minutes = max(0, min(59, intval($_GET['minutes'] ?? 0)));
$seconds = max(0, min(59, intval($_GET['seconds'] ?? 0)));
$elapsed_display = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

$sql_open_tickets = mysqli_query($mysqli, "SELECT client_name, ticket_id, ticket_number, ticket_prefix, ticket_status_name, ticket_subject FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_closed_at IS NULL
    " . clientScopeSql('ticket_client_id') . "
    ORDER BY ticket_status ASC, ticket_id DESC"
);

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_lead = 0 AND client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stopwatch mr-2"></i>Save Time Entry</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off" id="quickTimerSaveForm">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="entry_mode" id="quickTimerEntryMode" value="existing">
    <input type="hidden" name="hours" value="<?= $hours ?>">
    <input type="hidden" name="minutes" value="<?= $minutes ?>">
    <input type="hidden" name="seconds" value="<?= $seconds ?>">

    <div class="modal-body">

        <div class="alert alert-dark d-flex align-items-center">
            <i class="fas fa-fw fa-stopwatch mr-2"></i>
            <span><strong class="text-monospace"><?= $elapsed_display ?></strong> logged &mdash; choose where to save it, or discard it below.</span>
        </div>

        <ul class="nav nav-pills nav-justified mb-3" id="quickTimerTabs">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#quickTimerExistingPane" data-entry-mode="existing"><i class="fa fa-fw fa-life-ring mr-2"></i>Existing Ticket</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#quickTimerNewPane" data-entry-mode="new"><i class="fa fa-fw fa-plus mr-2"></i>New Ticket</a>
            </li>
        </ul>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="quickTimerExistingPane">
                <div class="form-group">
                    <label>Ticket <strong class="text-danger">*</strong></label>
                    <select class="form-control select2" name="ticket_id" id="quickTimerTicketSelect" required>
                        <option value="">- Select a Ticket -</option>
                        <?php while ($row = mysqli_fetch_assoc($sql_open_tickets)) {
                            $ticket_id_opt = intval($row['ticket_id']);
                            $ticket_prefix_opt = escapeHtml($row['ticket_prefix']);
                            $ticket_number_opt = intval($row['ticket_number']);
                            $ticket_status_name_opt = escapeHtml($row['ticket_status_name']);
                            $client_name_opt = escapeHtml($row['client_name']);
                            $ticket_subject_opt = escapeHtml($row['ticket_subject']);
                            ?>
                            <option value="<?= $ticket_id_opt ?>">
                                <?= "$ticket_prefix_opt$ticket_number_opt ($ticket_status_name_opt) $client_name_opt - $ticket_subject_opt" ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="tab-pane fade" id="quickTimerNewPane">
                <div class="form-group">
                    <label>Client <strong class="text-danger">*</strong></label>
                    <select class="form-control select2" name="client_id" id="quickTimerClientSelect" disabled>
                        <option value="">- Select a Client -</option>
                        <?php while ($row = mysqli_fetch_assoc($sql_clients)) {
                            $client_id_opt = intval($row['client_id']);
                            $client_name_opt = escapeHtml($row['client_name']);
                            ?>
                            <option value="<?= $client_id_opt ?>"><?= $client_name_opt ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <input type="text" class="form-control" name="subject" id="quickTimerSubject" placeholder="Subject" maxlength="500" disabled>
                </div>
                <div class="form-group mb-0">
                    <label>Details</label>
                    <textarea class="form-control" name="details" id="quickTimerDetails" rows="3" placeholder="Optional details"></textarea>
                </div>
            </div>

        </div>

        <div class="form-group mb-0 mt-3">
            <label>Note</label>
            <input type="text" class="form-control" name="note" placeholder="Time logged via quick timer" maxlength="500">
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="save_quick_timer" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Time</button>
        <button type="button" class="btn btn-outline-danger" id="quickTimerDiscardBtn"><i class="fas fa-trash mr-2"></i>Discard</button>
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
    </div>
</form>

<script>
(function () {
    $('#quickTimerTabs a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
        var mode = $(e.target).data('entry-mode');
        $('#quickTimerEntryMode').val(mode);

        var existingActive = mode === 'existing';
        $('#quickTimerTicketSelect').prop('disabled', !existingActive).prop('required', existingActive);
        $('#quickTimerClientSelect').prop('disabled', existingActive).prop('required', !existingActive);
        $('#quickTimerSubject').prop('disabled', existingActive).prop('required', !existingActive);
    });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
