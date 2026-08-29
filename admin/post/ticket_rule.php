<?php

// Ticket Rules

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$ticket_rule_condition_fields = ['from_email', 'from_domain', 'subject', 'body', 'ticket_source', 'client_id', 'contact_id'];
$ticket_rule_condition_operators = ['equals', 'contains', 'starts_with', 'ends_with', 'regex'];
$ticket_rule_action_types = ['set_priority', 'set_category', 'set_status', 'assign_to', 'apply_template', 'add_watcher', 'set_billable', 'resolve', 'delete'];
// Fields whose value is a lookup key, not free text - the operator is always 'equals' for these
$ticket_rule_condition_lookup_fields = ['ticket_source', 'client_id', 'contact_id'];

if (isset($_POST['add_ticket_rule'])) {

    validateCSRFToken();

    $name = escapeSql($_POST['name']);
    $match_type = $_POST['match_type'] === 'any' ? 'any' : 'all';
    $order = intval($_POST['rule_order']);
    $stop_processing = isset($_POST['stop_processing']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    mysqli_query($mysqli, "INSERT INTO ticket_rules SET ticket_rule_name = '$name', ticket_rule_match_type = '$match_type',
        ticket_rule_order = $order, ticket_rule_stop_processing = $stop_processing, ticket_rule_active = $active");

    $ticket_rule_id = mysqli_insert_id($mysqli);

    logAudit("Ticket Rule", "Create", "$session_name created ticket rule $name", 0, $ticket_rule_id);

    flashAlert("Ticket Rule <strong>$name</strong> created - add conditions and actions from its detail page");

    redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");

}

if (isset($_POST['edit_ticket_rule'])) {

    validateCSRFToken();

    $ticket_rule_id = intval($_POST['ticket_rule_id']);
    $name = escapeSql($_POST['name']);
    $match_type = $_POST['match_type'] === 'any' ? 'any' : 'all';
    $order = intval($_POST['rule_order']);
    $stop_processing = isset($_POST['stop_processing']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    mysqli_query($mysqli, "UPDATE ticket_rules SET ticket_rule_name = '$name', ticket_rule_match_type = '$match_type',
        ticket_rule_order = $order, ticket_rule_stop_processing = $stop_processing, ticket_rule_active = $active
        WHERE ticket_rule_id = $ticket_rule_id");

    logAudit("Ticket Rule", "Edit", "$session_name edited ticket rule $name", 0, $ticket_rule_id);

    flashAlert("Ticket Rule <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['archive_ticket_rule'])) {

    validateCSRFToken();

    $ticket_rule_id = intval($_GET['archive_ticket_rule']);

    $ticket_rule_name = escapeSql(getFieldById('ticket_rules', $ticket_rule_id, 'ticket_rule_name'));

    mysqli_query($mysqli, "UPDATE ticket_rules SET ticket_rule_archived_at = NOW() WHERE ticket_rule_id = $ticket_rule_id");

    logAudit("Ticket Rule", "Delete", "$session_name deleted ticket rule $ticket_rule_name", 0, $ticket_rule_id);

    flashAlert("Ticket Rule <strong>$ticket_rule_name</strong> deleted", 'error');

    redirect("ticket_rules.php");

}

if (isset($_POST['add_ticket_rule_condition'])) {

    validateCSRFToken();

    $ticket_rule_id = intval($_POST['ticket_rule_id']);
    $field = $_POST['field'];
    $operator = $_POST['operator'];

    if (!in_array($field, $ticket_rule_condition_fields)) {
        flashAlert("Invalid condition field", 'error');
        redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
    }

    // Lookup fields (client/contact/source) are always an exact match - the
    // value comes from a dedicated dropdown, never the free-text input, so a
    // regex/contains posted against a tampered form can't sneak through.
    if (in_array($field, $ticket_rule_condition_lookup_fields)) {
        $operator = 'equals';
        $value = match ($field) {
            'ticket_source' => $_POST['value_source'] ?? '',
            'client_id'     => strval(intval($_POST['value_client'] ?? 0)),
            'contact_id'    => strval(intval($_POST['value_contact'] ?? 0)),
        };
    } else {
        if (!in_array($operator, $ticket_rule_condition_operators)) {
            flashAlert("Invalid condition operator", 'error');
            redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
        }
        $value = trim($_POST['value'] ?? '');
    }

    if ($value === '') {
        flashAlert("Condition value cannot be empty", 'error');
        redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
    }

    if ($operator === 'regex' && @preg_match($value, '') === false) {
        flashAlert("That regex pattern is not valid - remember to wrap it in delimiters, e.g. <code>/example/i</code>", 'error');
        redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
    }

    $field_esc = escapeSql($field);
    $operator_esc = escapeSql($operator);
    // Not escapeSql() - it strip_tags()'s the input, which would mangle a
    // regex or a body-contains match that legitimately includes '<'/'>'.
    $value_esc = mysqli_real_escape_string($mysqli, $value);

    mysqli_query($mysqli, "INSERT INTO ticket_rule_conditions SET ticket_rule_condition_rule_id = $ticket_rule_id,
        ticket_rule_condition_field = '$field_esc', ticket_rule_condition_operator = '$operator_esc', ticket_rule_condition_value = '$value_esc'");

    logAudit("Ticket Rule", "Edit", "$session_name added a condition to ticket rule", 0, $ticket_rule_id);

    flashAlert("Condition added");

    redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");

}

if (isset($_GET['delete_ticket_rule_condition'])) {

    validateCSRFToken();

    $ticket_rule_condition_id = intval($_GET['delete_ticket_rule_condition']);

    $ticket_rule_id = intval(getFieldById('ticket_rule_conditions', $ticket_rule_condition_id, 'ticket_rule_condition_rule_id'));

    mysqli_query($mysqli, "DELETE FROM ticket_rule_conditions WHERE ticket_rule_condition_id = $ticket_rule_condition_id");

    logAudit("Ticket Rule", "Edit", "$session_name removed a condition from ticket rule", 0, $ticket_rule_id);

    flashAlert("Condition removed", 'error');

    redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");

}

if (isset($_POST['add_ticket_rule_action'])) {

    validateCSRFToken();

    $ticket_rule_id = intval($_POST['ticket_rule_id']);
    $action_type = $_POST['action_type'];

    if (!in_array($action_type, $ticket_rule_action_types)) {
        flashAlert("Invalid action type", 'error');
        redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
    }

    $value = match ($action_type) {
        'set_priority'   => $_POST['value_priority'] ?? '',
        'set_status'     => strval(intval($_POST['value_status'] ?? 0)),
        'assign_to'      => strval(intval($_POST['value_tech'] ?? 0)),
        'apply_template' => strval(intval($_POST['value_template'] ?? 0)),
        'set_category', 'add_watcher' => trim($_POST['value'] ?? ''),
        // '0' is a real, meaningful choice here ("not billable") - not "nothing selected"
        'set_billable'   => (isset($_POST['value_billable']) && $_POST['value_billable'] === '1') ? '1' : '0',
        // No user-supplied value - the action itself (resolve/delete the ticket) is the whole point
        'resolve', 'delete' => '1',
    };

    if ($value === '' || ($value === '0' && $action_type !== 'set_billable')) {
        flashAlert("Action value cannot be empty", 'error');
        redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
    }

    if ($action_type === 'add_watcher' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        flashAlert("Watcher value must be a valid email address", 'error');
        redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");
    }

    $action_type_esc = escapeSql($action_type);
    $value_esc = mysqli_real_escape_string($mysqli, $value);

    mysqli_query($mysqli, "INSERT INTO ticket_rule_actions SET ticket_rule_action_rule_id = $ticket_rule_id,
        ticket_rule_action_type = '$action_type_esc', ticket_rule_action_value = '$value_esc'");

    logAudit("Ticket Rule", "Edit", "$session_name added an action to ticket rule", 0, $ticket_rule_id);

    flashAlert("Action added");

    redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");

}

if (isset($_GET['delete_ticket_rule_action'])) {

    validateCSRFToken();

    $ticket_rule_action_id = intval($_GET['delete_ticket_rule_action']);

    $ticket_rule_id = intval(getFieldById('ticket_rule_actions', $ticket_rule_action_id, 'ticket_rule_action_rule_id'));

    mysqli_query($mysqli, "DELETE FROM ticket_rule_actions WHERE ticket_rule_action_id = $ticket_rule_action_id");

    logAudit("Ticket Rule", "Edit", "$session_name removed an action from ticket rule", 0, $ticket_rule_id);

    flashAlert("Action removed", 'error');

    redirect("ticket_rule.php?ticket_rule_id=$ticket_rule_id");

}
