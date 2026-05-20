<?php
require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

$sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
$ticket_number = intval($row['ticket_number']);

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title">
        <i class="fa fa-fw fa-cube mr-2"></i>
        Apply Template to <?php echo "$ticket_prefix$ticket_number"; ?>
    </h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Template <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                </div>
                <select class="form-control select2" name="ticket_template_id" required>
                    <option value="">- Choose a Template -</option>
                    <?php
                    $sql_ticket_templates = mysqli_query($mysqli, "
                        SELECT tt.ticket_template_id,
                               tt.ticket_template_name,
                               COUNT(ttt.task_template_id) AS task_count
                        FROM ticket_templates tt
                        LEFT JOIN task_templates ttt
                            ON tt.ticket_template_id = ttt.task_template_ticket_template_id
                        WHERE tt.ticket_template_archived_at IS NULL
                        GROUP BY tt.ticket_template_id
                        ORDER BY tt.ticket_template_name ASC
                    ");
                    while ($row = mysqli_fetch_assoc($sql_ticket_templates)) {
                        $ticket_template_id_select = intval($row['ticket_template_id']);
                        $ticket_template_name_select = nullable_htmlentities($row['ticket_template_name']);
                        $task_count = intval($row['task_count']);
                        ?>
                        <option value="<?php echo $ticket_template_id_select; ?>">
                            <?php echo $ticket_template_name_select; ?> (<?php echo $task_count; ?> tasks)
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Subject &amp; Details</label>
            <div class="custom-control custom-radio">
                <input type="radio" id="merge_mode_append" name="merge_mode" value="append" class="custom-control-input" checked>
                <label class="custom-control-label" for="merge_mode_append">
                    Append template subject &amp; details to existing
                </label>
            </div>
            <div class="custom-control custom-radio">
                <input type="radio" id="merge_mode_overwrite" name="merge_mode" value="overwrite" class="custom-control-input">
                <label class="custom-control-label" for="merge_mode_overwrite">
                    Overwrite existing subject &amp; details
                </label>
            </div>
            <small class="form-text text-muted">
                Template tasks are always added to the ticket. Empty template fields are skipped in either mode.
            </small>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="apply_template_to_ticket" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Apply
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php require_once '../../../includes/modal_footer.php'; ?>
