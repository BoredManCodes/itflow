<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT client_name, ticket_client_id, ticket_number, ticket_prefix, ticket_status FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    WHERE ticket_id = $ticket_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$ticket_prefix = escapeHtml($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);
$ticket_status = intval($row['ticket_status']);
$client_name = escapeHtml($row['client_name']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

// Tasks still open block resolving the ticket - same rule the reply form's status select uses
$task_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(task_id) FROM tasks WHERE task_ticket_id = $ticket_id"))[0]);
$completed_task_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(task_id) FROM tasks WHERE task_ticket_id = $ticket_id AND task_completed_at IS NOT NULL"))[0]);
$tasks_block_resolve = $task_count !== $completed_task_count;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-list-check me-2"></i>Editing status: <strong><?= "$ticket_prefix$ticket_number" ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label>Status</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-list-check"></i></span>
                <select class="form-select select2" name="status" required>
                    <!-- New and Closed are system-managed and change through their own actions, not this one -->
                    <?php
                    $status_snippet = '';
                    if ($tasks_block_resolve) {
                        $status_snippet = "AND ticket_status_id != 4";
                    }
                    $sql_ticket_status = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses WHERE ticket_status_id != 1 AND ticket_status_id != 5 AND ticket_status_active = 1 $status_snippet ORDER BY ticket_status_order");
                    while ($status_row = mysqli_fetch_assoc($sql_ticket_status)) {
                        $ticket_status_id_select = intval($status_row['ticket_status_id']);
                        $ticket_status_name_select = escapeHtml($status_row['ticket_status_name']);
                        ?>
                        <option value="<?= $ticket_status_id_select ?>" <?php if ($ticket_status == $ticket_status_id_select) { echo 'selected'; } ?>><?= $ticket_status_name_select ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if ($tasks_block_resolve) { ?>
                <div class="form-text"><?= $task_count - $completed_task_count ?> task<?= ($task_count - $completed_task_count) == 1 ? '' : 's' ?> still open, so Resolved isn't offered here</div>
            <?php } ?>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket_status" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php

require_once '../../../includes/modal_footer.php';
