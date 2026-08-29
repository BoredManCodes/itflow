<?php

/*
 * Ticket Rules
 * Automatic routing of newly created tickets: matches conditions (client,
 * contact, and - for email-parsed tickets - sender address/domain/subject/body)
 * against admin-managed rules and applies actions (priority, category, status,
 * assignee, template tasks, watchers).
 *
 * Stage 1: schema + engine only. Nothing calls applyTicketRules() yet - that
 * lands in a later PR once the admin UI (to manage rules) exists.
 */

/**
 * Evaluate active ticket_rules against a just-created ticket and apply the
 * actions of the first (or every, per-rule) matching rule.
 *
 * $context carries fields that only exist for email-parsed tickets and are not
 * otherwise stored on the ticket row by the time this runs. Callers that
 * aren't the email parser simply omit them, and any condition on those fields
 * will not match.
 *
 * @param int   $ticket_id
 * @param array $context {
 *     @type string $from_email  Sender address (email-parsed tickets only)
 *     @type string $from_domain Sender domain (email-parsed tickets only)
 *     @type string $subject     Raw email subject (email-parsed tickets only)
 *     @type string $body        Raw email body (email-parsed tickets only)
 * }
 * @return int Number of rules whose actions were applied
 */
function applyTicketRules($ticket_id, $context = []) {

    global $mysqli;

    $ticket_id = intval($ticket_id);
    if (!$ticket_id) {
        return 0;
    }

    $ticket_sql = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_contact_id, ticket_source FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
    if (!$ticket_sql || !mysqli_num_rows($ticket_sql)) {
        return 0;
    }
    $ticket = mysqli_fetch_assoc($ticket_sql);

    $fields = [
        'client_id'   => intval($ticket['ticket_client_id']),
        'contact_id'  => intval($ticket['ticket_contact_id']),
        'ticket_source' => (string) $ticket['ticket_source'],
        'from_email'  => (string) ($context['from_email'] ?? ''),
        'from_domain' => (string) ($context['from_domain'] ?? ''),
        'subject'     => (string) ($context['subject'] ?? ''),
        'body'        => (string) ($context['body'] ?? ''),
    ];

    $rules_applied = 0;

    $rules_sql = mysqli_query($mysqli, "SELECT ticket_rule_id, ticket_rule_name, ticket_rule_match_type, ticket_rule_stop_processing
        FROM ticket_rules
        WHERE ticket_rule_active = 1 AND ticket_rule_archived_at IS NULL
        ORDER BY ticket_rule_order ASC, ticket_rule_id ASC");

    while ($rule = mysqli_fetch_assoc($rules_sql)) {
        $rule_id = intval($rule['ticket_rule_id']);

        if (!ticketRuleMatches($mysqli, $rule_id, $rule['ticket_rule_match_type'], $fields)) {
            continue;
        }

        applyTicketRuleActions($mysqli, $ticket_id, $rule_id);
        $rule_name = escapeSql($rule['ticket_rule_name']);
        logTicketHistory($ticket_id, "Rule matched: $rule_name");
        $rules_applied++;

        if (intval($rule['ticket_rule_stop_processing'])) {
            break;
        }
    }

    return $rules_applied;
}

/**
 * Whether a rule's conditions match the given ticket fields.
 */
function ticketRuleMatches($mysqli, $rule_id, $match_type, $fields) {

    $rule_id = intval($rule_id);

    $conditions_sql = mysqli_query($mysqli, "SELECT ticket_rule_condition_field, ticket_rule_condition_operator, ticket_rule_condition_value
        FROM ticket_rule_conditions WHERE ticket_rule_condition_rule_id = $rule_id");

    if (!$conditions_sql || !mysqli_num_rows($conditions_sql)) {
        // A rule with no conditions matches nothing - it can't have been
        // meant to fire on every single ticket.
        return false;
    }

    $any_matched = false;

    while ($condition = mysqli_fetch_assoc($conditions_sql)) {
        $field = $condition['ticket_rule_condition_field'];
        $operator = $condition['ticket_rule_condition_operator'];
        $value = $condition['ticket_rule_condition_value'];

        $field_value = $fields[$field] ?? null;
        $matched = ticketRuleConditionMatches($field_value, $operator, $value);

        if ($match_type === 'any') {
            if ($matched) {
                $any_matched = true;
                break;
            }
        } else {
            // 'all' - a single miss fails the whole rule
            if (!$matched) {
                return false;
            }
            $any_matched = true;
        }
    }

    return $any_matched;
}

/**
 * Evaluate a single condition. $field_value is null when the condition names
 * an unknown field or a field this ticket-creation path never populated
 * (e.g. from_email on a manually-added ticket) - such a condition never matches.
 */
function ticketRuleConditionMatches($field_value, $operator, $value) {

    if (is_null($field_value) || $field_value === '') {
        return false;
    }

    switch ($operator) {
        case 'equals':
            return strcasecmp($field_value, $value) === 0;
        case 'contains':
            return stripos($field_value, $value) !== false;
        case 'starts_with':
            return stripos($field_value, $value) === 0;
        case 'ends_with':
            $value_len = strlen($value);
            return $value_len === 0 || strtolower(substr($field_value, -$value_len)) === strtolower($value);
        case 'regex':
            // Admin-entered pattern - a malformed one must not fatal ticket
            // creation. preg_match returns false (and warns, suppressed here)
            // on a bad pattern rather than throwing.
            $result = @preg_match($value, $field_value);
            if ($result === false) {
                logApp("Ticket-Rules", "warning", "Invalid regex in rule condition, skipped: $value");
                return false;
            }
            return $result === 1;
        default:
            return false;
    }
}

/**
 * Apply every action attached to a matched rule.
 */
function applyTicketRuleActions($mysqli, $ticket_id, $rule_id) {

    $ticket_id = intval($ticket_id);
    $rule_id = intval($rule_id);

    $actions_sql = mysqli_query($mysqli, "SELECT ticket_rule_action_type, ticket_rule_action_value
        FROM ticket_rule_actions WHERE ticket_rule_action_rule_id = $rule_id");

    while ($action = mysqli_fetch_assoc($actions_sql)) {
        applyTicketRuleAction($mysqli, $ticket_id, $action['ticket_rule_action_type'], $action['ticket_rule_action_value']);
    }
}

function applyTicketRuleAction($mysqli, $ticket_id, $action_type, $action_value) {

    $ticket_id = intval($ticket_id);

    switch ($action_type) {
        case 'set_priority':
            $priority = escapeSql($action_value);
            mysqli_query($mysqli, "UPDATE tickets SET ticket_priority = '$priority' WHERE ticket_id = $ticket_id");
            applyTicketSla($ticket_id);
            break;

        case 'set_category':
            $category = escapeSql($action_value);
            mysqli_query($mysqli, "UPDATE tickets SET ticket_category = '$category' WHERE ticket_id = $ticket_id");
            break;

        case 'set_status':
            $status_id = intval($action_value);
            $status_sql = mysqli_query($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_id = $status_id LIMIT 1");
            if ($status_sql && mysqli_num_rows($status_sql)) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_status = $status_id WHERE ticket_id = $ticket_id");
            }
            break;

        case 'assign_to':
            $user_id = intval($action_value);
            $user_sql = mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = $user_id AND user_type = 1 AND user_status = 1 AND user_archived_at IS NULL LIMIT 1");
            if ($user_sql && mysqli_num_rows($user_sql)) {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_assigned_to = $user_id WHERE ticket_id = $ticket_id");
                syncTicketSlaClock($ticket_id);

                $client_sql = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id LIMIT 1");
                $ticket_row = mysqli_fetch_assoc($client_sql);
                $ticket_prefix = escapeSql($ticket_row['ticket_prefix']);
                $ticket_number = intval($ticket_row['ticket_number']);
                $ticket_subject = escapeSql($ticket_row['ticket_subject']);
                $client_id = intval($ticket_row['ticket_client_id']);
                $client_uri = $client_id ? "&client_id=$client_id" : '';

                $notification_text = "Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject has been assigned to you by a ticket rule";
                $notification_action = "/agent/ticket.php?ticket_id=$ticket_id$client_uri";
                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$notification_text', notification_action = '$notification_action', notification_client_id = $client_id, notification_user_id = $user_id");
                sendPushNotification($user_id, 'Ticket', $notification_text, $notification_action);
            }
            break;

        case 'apply_template':
            // Seeds the template's task checklist onto the ticket - the ticket
            // already has real subject/details from however it was created, so
            // a rule must not overwrite them with the template's placeholder text.
            addTasksFromTicketTemplate($ticket_id, intval($action_value));
            break;

        case 'add_watcher':
            $watcher_email = escapeSql($action_value);
            if (filter_var($action_value, FILTER_VALIDATE_EMAIL)) {
                mysqli_query($mysqli, "INSERT INTO ticket_watchers SET watcher_email = '$watcher_email', watcher_ticket_id = $ticket_id");
            }
            break;

        case 'set_billable':
            $billable = intval($action_value) ? 1 : 0;
            mysqli_query($mysqli, "UPDATE tickets SET ticket_billable = $billable WHERE ticket_id = $ticket_id");
            break;

        case 'resolve':
            // Mirrors agent/post/ticket.php's resolve_ticket handler. Safe to run
            // mid-creation-flow - it doesn't remove anything, so whatever the
            // calling creation path still does afterward (attachments, watchers,
            // notifications) keeps working against a ticket that still exists.
            mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id");
            syncTicketSlaClock($ticket_id);
            setTicketResolutionSlaMet($ticket_id);
            logAudit("Ticket", "Resolve", "Ticket rule resolved the ticket", 0, $ticket_id);
            triggerCustomAction('ticket_resolve', $ticket_id);
            break;

        case 'delete':
            // Mirrors agent/post/ticket.php's delete_ticket handler (hard delete,
            // same cascade). Unlike resolve, this removes the ticket row while
            // applyTicketRules() runs mid-creation-flow (before the .eml
            // attachment, watchers, and "new ticket" notification are added on
            // most paths) - whatever the caller still does after this returns
            // will reference a ticket_id that no longer exists. Harmless orphan
            // rows/notifications can result; nothing fatal.
            $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
            mysqli_query($mysqli, "DELETE FROM tickets WHERE ticket_id = $ticket_id");
            mysqli_query($mysqli, "DELETE FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id");
            mysqli_query($mysqli, "DELETE FROM ticket_views WHERE view_ticket_id = $ticket_id");
            mysqli_query($mysqli, "DELETE FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
            mysqli_query($mysqli, "DELETE FROM ticket_attachments WHERE ticket_attachment_ticket_id = $ticket_id");
            // __DIR__-anchored, not a relative path - this function is called from many
            // different entry points (agent/, admin/, client/, cron/, api/v1/tickets/) whose
            // working directories sit at different depths under the app root, unlike
            // agent/post/ticket.php's own delete handler which only ever runs from agent/.
            removeDirectory(__DIR__ . "/../uploads/tickets/$ticket_id");
            $ticket_prefix = escapeSql($ticket_row['ticket_prefix'] ?? '');
            $ticket_number = intval($ticket_row['ticket_number'] ?? 0);
            logAudit("Ticket", "Delete", "Ticket rule deleted $ticket_prefix$ticket_number along with all replies", 0, $ticket_id);
            triggerCustomAction('ticket_delete', $ticket_id);
            break;
    }
}
