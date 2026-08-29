<?php

require_once "includes/inc_all_admin.php";

if (isset($_GET['ticket_rule_id'])) {
    $ticket_rule_id = intval($_GET['ticket_rule_id']);
}

$sql_ticket_rule = mysqli_query($mysqli, "SELECT ticket_rule_name, ticket_rule_match_type, ticket_rule_order, ticket_rule_stop_processing,
    ticket_rule_active, ticket_rule_created_at FROM ticket_rules WHERE ticket_rule_id = $ticket_rule_id LIMIT 1");

if (mysqli_num_rows($sql_ticket_rule) == 0) {
    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";
    require_once "../includes/footer.php";
    exit();
}

$row = mysqli_fetch_assoc($sql_ticket_rule);

$ticket_rule_name = escapeHtml($row['ticket_rule_name']);
$ticket_rule_match_type = escapeHtml($row['ticket_rule_match_type']);
$ticket_rule_order = intval($row['ticket_rule_order']);
$ticket_rule_stop_processing = intval($row['ticket_rule_stop_processing']);
$ticket_rule_active = intval($row['ticket_rule_active']);
$ticket_rule_created_at = escapeHtml($row['ticket_rule_created_at']);

// Field/action option labels, shared between the add forms and the row display below
$condition_field_labels = [
    'from_email'    => 'Sender email',
    'from_domain'   => 'Sender domain',
    'subject'       => 'Subject',
    'body'          => 'Body',
    'ticket_source' => 'Source',
    'client_id'     => 'Client',
    'contact_id'    => 'Contact',
];
$condition_operator_labels = [
    'equals'      => 'equals',
    'contains'    => 'contains',
    'starts_with' => 'starts with',
    'ends_with'   => 'ends with',
    'regex'       => 'matches regex',
];
$action_type_labels = [
    'set_priority'   => 'Set priority',
    'set_category'   => 'Set category',
    'set_status'     => 'Set status',
    'assign_to'      => 'Assign to',
    'apply_template' => 'Apply template (adds its tasks)',
    'add_watcher'    => 'Add watcher email',
];

// Lookups for the value dropdowns
$clients = [];
$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
while ($r = mysqli_fetch_assoc($sql_clients)) {
    $clients[intval($r['client_id'])] = $r['client_name'];
}

$contacts = [];
$sql_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name, client_name FROM contacts
    LEFT JOIN clients ON clients.client_id = contacts.contact_client_id
    WHERE contact_archived_at IS NULL ORDER BY client_name ASC, contact_name ASC");
while ($r = mysqli_fetch_assoc($sql_contacts)) {
    $contacts[intval($r['contact_id'])] = $r['contact_name'] . ' - ' . $r['client_name'];
}

$techs = [];
$sql_techs = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($r = mysqli_fetch_assoc($sql_techs)) {
    $techs[intval($r['user_id'])] = $r['user_name'];
}

$ticket_templates_list = [];
$sql_ticket_templates = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_name FROM ticket_templates WHERE ticket_template_archived_at IS NULL ORDER BY ticket_template_name ASC");
while ($r = mysqli_fetch_assoc($sql_ticket_templates)) {
    $ticket_templates_list[intval($r['ticket_template_id'])] = $r['ticket_template_name'];
}

$ticket_statuses_list = [];
$sql_ticket_statuses = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name FROM ticket_statuses WHERE ticket_status_active = 1 ORDER BY ticket_status_order ASC");
while ($r = mysqli_fetch_assoc($sql_ticket_statuses)) {
    $ticket_statuses_list[intval($r['ticket_status_id'])] = $r['ticket_status_name'];
}

// Existing conditions / actions
$sql_conditions = mysqli_query($mysqli, "SELECT ticket_rule_condition_id, ticket_rule_condition_field, ticket_rule_condition_operator, ticket_rule_condition_value
    FROM ticket_rule_conditions WHERE ticket_rule_condition_rule_id = $ticket_rule_id ORDER BY ticket_rule_condition_id ASC");

$sql_actions = mysqli_query($mysqli, "SELECT ticket_rule_action_id, ticket_rule_action_type, ticket_rule_action_value
    FROM ticket_rule_actions WHERE ticket_rule_action_rule_id = $ticket_rule_id ORDER BY ticket_rule_action_id ASC");

/**
 * Render an action/condition value for display, resolving IDs to names where
 * the value is a lookup key rather than plain text.
 */
function ticketRuleDisplayValue($mysqli, $field_or_type, $value) {
    global $clients, $contacts, $techs, $ticket_templates_list, $ticket_statuses_list;

    switch ($field_or_type) {
        case 'client_id':
            return escapeHtml($clients[intval($value)] ?? "Client #$value");
        case 'contact_id':
            return escapeHtml($contacts[intval($value)] ?? "Contact #$value");
        case 'assign_to':
            return escapeHtml($techs[intval($value)] ?? "User #$value");
        case 'apply_template':
            return escapeHtml($ticket_templates_list[intval($value)] ?? "Template #$value");
        case 'set_status':
            return escapeHtml($ticket_statuses_list[intval($value)] ?? "Status #$value");
        default:
            return escapeHtml($value);
    }
}

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item"><a href="users.php">Admin</a></li>
    <li class="breadcrumb-item"><a href="ticket_rules.php">Ticket Rules</a></li>
    <li class="breadcrumb-item active"><i class="fas fa-random mr-2"></i><?= $ticket_rule_name ?></li>
</ol>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title mt-1"><?= $ticket_rule_name ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool btn-sm ajax-modal" data-modal-url="modals/ticket_rule/ticket_rule_edit.php?id=<?= $ticket_rule_id ?>">
                <i class="fas fa-edit"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <span class="badge badge-secondary mr-2"><?= strtoupper($ticket_rule_match_type) ?> conditions</span>
        <?php if ($ticket_rule_stop_processing) { ?>
            <span class="badge badge-info mr-2">Stops further rules</span>
        <?php } else { ?>
            <span class="badge badge-light mr-2">Continues to later rules</span>
        <?php } ?>
        <span class="badge <?= $ticket_rule_active ? 'badge-success' : 'badge-secondary' ?> mr-2"><?= $ticket_rule_active ? 'Active' : 'Inactive' ?></span>
        <span class="text-muted">Order <?= $ticket_rule_order ?> · Created <?= $ticket_rule_created_at ?></span>
    </div>
</div>

<div class="row">

    <div class="col-md-6">
        <div class="card card-dark">
            <div class="card-header py-2">
                <h5 class="card-title mt-1"><i class="fa fa-fw fa-filter mr-2"></i>Conditions</h5>
            </div>
            <div class="card-body">

                <table class="table table-sm">
                    <?php while ($c = mysqli_fetch_assoc($sql_conditions)) {
                        $condition_id = intval($c['ticket_rule_condition_id']);
                        $field = $c['ticket_rule_condition_field'];
                        $operator = $c['ticket_rule_condition_operator'];
                        $value = $c['ticket_rule_condition_value'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= escapeHtml($condition_field_labels[$field] ?? $field) ?></strong>
                                <?= escapeHtml($condition_operator_labels[$operator] ?? $operator) ?>
                                <span class="text-dark"><?= ticketRuleDisplayValue($mysqli, $field, $value) ?></span>
                            </td>
                            <td class="text-right" style="width: 1%;">
                                <a class="text-danger confirm-link" href="post.php?delete_ticket_rule_condition=<?= $condition_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>

                <hr>

                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="ticket_rule_id" value="<?= $ticket_rule_id ?>">

                    <div class="form-group">
                        <label class="text-muted">Add a condition</label>
                        <select class="form-control form-control-sm mb-2" name="field" id="conditionField">
                            <?php foreach ($condition_field_labels as $field_key => $field_label) { ?>
                                <option value="<?= $field_key ?>"><?= $field_label ?></option>
                            <?php } ?>
                        </select>

                        <select class="form-control form-control-sm mb-2 condition-operator-select" name="operator">
                            <?php foreach ($condition_operator_labels as $operator_key => $operator_label) { ?>
                                <option value="<?= $operator_key ?>"><?= $operator_label ?></option>
                            <?php } ?>
                        </select>

                        <div class="condition-value-wrap" data-kind="text">
                            <input type="text" class="form-control form-control-sm mb-2" name="value" placeholder="Value or /regex/i">
                        </div>

                        <div class="condition-value-wrap d-none" data-kind="source">
                            <select class="form-control form-control-sm mb-2" name="value_source" disabled>
                                <option value="Email">Email</option>
                                <option value="Agent">Agent</option>
                                <option value="Portal">Portal</option>
                                <option value="API">API</option>
                                <option value="Recurring">Recurring</option>
                            </select>
                        </div>

                        <div class="condition-value-wrap d-none" data-kind="client">
                            <select class="form-control form-control-sm mb-2 select2" name="value_client" disabled>
                                <?php foreach ($clients as $client_id_opt => $client_name_opt) { ?>
                                    <option value="<?= $client_id_opt ?>"><?= escapeHtml($client_name_opt) ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="condition-value-wrap d-none" data-kind="contact">
                            <select class="form-control form-control-sm mb-2 select2" name="value_contact" disabled>
                                <?php foreach ($contacts as $contact_id_opt => $contact_label_opt) { ?>
                                    <option value="<?= $contact_id_opt ?>"><?= escapeHtml($contact_label_opt) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_ticket_rule_condition" class="btn btn-primary btn-sm"><i class="fas fa-fw fa-plus mr-1"></i>Add condition</button>
                </form>

            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-dark">
            <div class="card-header py-2">
                <h5 class="card-title mt-1"><i class="fa fa-fw fa-bolt mr-2"></i>Actions</h5>
            </div>
            <div class="card-body">

                <table class="table table-sm">
                    <?php while ($a = mysqli_fetch_assoc($sql_actions)) {
                        $action_id = intval($a['ticket_rule_action_id']);
                        $action_type = $a['ticket_rule_action_type'];
                        $action_value = $a['ticket_rule_action_value'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= escapeHtml($action_type_labels[$action_type] ?? $action_type) ?>:</strong>
                                <span class="text-dark"><?= ticketRuleDisplayValue($mysqli, $action_type, $action_value) ?></span>
                            </td>
                            <td class="text-right" style="width: 1%;">
                                <a class="text-danger confirm-link" href="post.php?delete_ticket_rule_action=<?= $action_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>

                <hr>

                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="ticket_rule_id" value="<?= $ticket_rule_id ?>">

                    <div class="form-group">
                        <label class="text-muted">Add an action</label>
                        <select class="form-control form-control-sm mb-2" name="action_type" id="actionType">
                            <?php foreach ($action_type_labels as $type_key => $type_label) { ?>
                                <option value="<?= $type_key ?>"><?= $type_label ?></option>
                            <?php } ?>
                        </select>

                        <div class="action-value-wrap d-none" data-kind="text">
                            <input type="text" class="form-control form-control-sm mb-2" name="value" placeholder="Value">
                        </div>

                        <div class="action-value-wrap" data-kind="priority">
                            <select class="form-control form-control-sm mb-2" name="value_priority">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="action-value-wrap d-none" data-kind="status">
                            <select class="form-control form-control-sm mb-2" name="value_status" disabled>
                                <?php foreach ($ticket_statuses_list as $status_id_opt => $status_name_opt) { ?>
                                    <option value="<?= $status_id_opt ?>"><?= escapeHtml($status_name_opt) ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="action-value-wrap d-none" data-kind="tech">
                            <select class="form-control form-control-sm mb-2 select2" name="value_tech" disabled>
                                <?php foreach ($techs as $tech_id_opt => $tech_name_opt) { ?>
                                    <option value="<?= $tech_id_opt ?>"><?= escapeHtml($tech_name_opt) ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="action-value-wrap d-none" data-kind="template">
                            <select class="form-control form-control-sm mb-2 select2" name="value_template" disabled>
                                <?php foreach ($ticket_templates_list as $template_id_opt => $template_name_opt) { ?>
                                    <option value="<?= $template_id_opt ?>"><?= escapeHtml($template_name_opt) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_ticket_rule_action" class="btn btn-primary btn-sm"><i class="fas fa-fw fa-plus mr-1"></i>Add action</button>
                </form>

            </div>
        </div>
    </div>

</div>

<script>
(function () {
    // Toggling a select2-enhanced <select> itself does nothing visible - select2
    // hides the native element and renders its own widget as a sibling. So each
    // alternative value input lives in its own wrapper div, and that wrapper is
    // what gets shown/hidden; the control inside is only what gets disabled
    // (disabled inputs are excluded from the form submission).
    function renderValueWraps(wraps, activeKind) {
        wraps.forEach(function (wrap) {
            const show = wrap.dataset.kind === activeKind;
            wrap.classList.toggle('d-none', !show);
            wrap.querySelectorAll('input, select').forEach(function (el) {
                el.disabled = !show;
            });
        });
    }

    // ---- Condition value input follows the selected field ----
    const conditionField = document.getElementById('conditionField');
    const conditionValueWraps = Array.from(document.querySelectorAll('.condition-value-wrap'));
    const operatorSelect = document.querySelector('.condition-operator-select');

    function renderConditionInputs() {
        const field = conditionField.value;
        const forcedEquals = ['ticket_source', 'client_id', 'contact_id'].includes(field);
        const kind = field === 'ticket_source' ? 'source' : (field === 'client_id' ? 'client' : (field === 'contact_id' ? 'contact' : 'text'));

        renderValueWraps(conditionValueWraps, kind);

        operatorSelect.disabled = forcedEquals;
        operatorSelect.classList.toggle('d-none', forcedEquals);
    }

    conditionField.addEventListener('change', renderConditionInputs);
    renderConditionInputs();

    // ---- Action value input follows the selected action type ----
    const actionType = document.getElementById('actionType');
    const actionValueWraps = Array.from(document.querySelectorAll('.action-value-wrap'));
    const actionKindByType = {
        set_priority: 'priority',
        set_category: 'text',
        set_status: 'status',
        assign_to: 'tech',
        apply_template: 'template',
        add_watcher: 'text',
    };

    function renderActionInputs() {
        const kind = actionKindByType[actionType.value] || 'text';
        renderValueWraps(actionValueWraps, kind);
    }

    actionType.addEventListener('change', renderActionInputs);
    renderActionInputs();
})();
</script>

<?php
require_once "../includes/footer.php";
