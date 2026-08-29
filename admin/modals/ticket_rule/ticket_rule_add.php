<?php

require_once '../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-random mr-2"></i>New Ticket Rule</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Rule Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-random"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="e.g. Acme Corp -> High priority" maxlength="200" required autofocus>
            </div>
            <small class="text-muted">Add conditions and actions once the rule exists, from its detail page.</small>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label>Match</label>
                <select class="form-control" name="match_type">
                    <option value="all">ALL conditions must match</option>
                    <option value="any">ANY condition can match</option>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label>Order <small class="text-muted">(lower runs first)</small></label>
                <input type="number" class="form-control" name="rule_order" value="0">
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="stop_processing" value="1" id="ruleStopProcessingSwitch" checked>
                <label class="custom-control-label" for="ruleStopProcessingSwitch">Stop processing further rules once this one matches</label>
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="active" value="1" id="ruleActiveSwitch" checked>
                <label class="custom-control-label" for="ruleActiveSwitch">Active</label>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ticket_rule" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create Rule</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
