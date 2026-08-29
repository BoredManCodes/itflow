<?php

require_once '../../includes/modal_header.php';

$ticket_rule_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT ticket_rule_name, ticket_rule_match_type, ticket_rule_order, ticket_rule_stop_processing, ticket_rule_active
    FROM ticket_rules WHERE ticket_rule_id = $ticket_rule_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$ticket_rule_name = escapeHtml($row['ticket_rule_name']);
$ticket_rule_match_type = escapeHtml($row['ticket_rule_match_type']);
$ticket_rule_order = intval($row['ticket_rule_order']);
$ticket_rule_stop_processing = intval($row['ticket_rule_stop_processing']);
$ticket_rule_active = intval($row['ticket_rule_active']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-random mr-2"></i>Editing rule</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_rule_id" value="<?= $ticket_rule_id ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Rule Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-random"></i></span>
                </div>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= $ticket_rule_name ?>" required autofocus>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Match</label>
                <select class="form-control" name="match_type">
                    <option value="all" <?php if ($ticket_rule_match_type == 'all') { echo "selected"; } ?>>ALL conditions must match</option>
                    <option value="any" <?php if ($ticket_rule_match_type == 'any') { echo "selected"; } ?>>ANY condition can match</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Order <small class="text-muted">(lower runs first)</small></label>
                <input type="number" class="form-control" name="rule_order" value="<?= $ticket_rule_order ?>">
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="stop_processing" value="1" id="ruleStopProcessingSwitch" <?php if ($ticket_rule_stop_processing) { echo "checked"; } ?>>
                <label class="custom-control-label" for="ruleStopProcessingSwitch">Stop processing further rules once this one matches</label>
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="active" value="1" id="ruleActiveSwitch" <?php if ($ticket_rule_active) { echo "checked"; } ?>>
                <label class="custom-control-label" for="ruleActiveSwitch">Active</label>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_rule" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
