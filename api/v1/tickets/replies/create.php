<?php

/*
 * POST /api/v1/tickets/replies/create.php
 *
 * Adds a reply (and/or a status change, and/or time worked) to an existing
 * ticket. Mirrors the data-side behavior of `agent/post/ticket.php`
 * add_ticket_reply, minus CSRF and session-user concerns.
 *
 * Required body fields:
 *   ticket_id      int    target ticket
 *
 * Either `ticket_reply` (non-empty) or `status` (>0) must be supplied —
 * status-only calls are allowed so integrations can close/transition a
 * ticket without writing a comment.
 *
 * Optional body fields:
 *   ticket_reply        string  reply body (may contain HTML, gets SQL-escaped)
 *   public_reply_type   1 = Public (no email)
 *                       2 = Public + email   (rejected — not supported by the API)
 *                       3 = Internal note    [default]
 *   hours, minutes, seconds   time worked components (default 0)
 *   status                    new ticket_status id; if 4, ticket_resolved_at is set
 *
 * Reply rows are inserted with `ticket_reply_by = 0` — same convention as
 * `api/v1/tickets/create.php`, which sets `ticket_created_by = 0` for
 * API-originated rows.
 */

require_once '../../validate_api_key.php';

require_once '../../require_post_method.php';

$insert_id = false;

$ticket_id    = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$ticket_reply = isset($_POST['ticket_reply']) ? $_POST['ticket_reply'] : '';
$status_in    = isset($_POST['status']) ? intval($_POST['status']) : 0;

// Default to Internal (3): if a caller forgets to set public_reply_type,
// the reply stays invisible to the customer. The opposite default would
// silently leak whatever the integration wrote out to the contact's portal
// view on the next page load.
$reply_type_in = isset($_POST['public_reply_type']) ? intval($_POST['public_reply_type']) : 3;

$hours   = isset($_POST['hours'])   ? intval($_POST['hours'])   : 0;
$minutes = isset($_POST['minutes']) ? intval($_POST['minutes']) : 0;
$seconds = isset($_POST['seconds']) ? intval($_POST['seconds']) : 0;
$ticket_reply_time_worked = sanitizeInput(sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds));

// Reject Public + email — the API doesn't dispatch mail, so accepting
// type 2 would let callers think they emailed the customer when they
// didn't. Better to fail loudly.
if ($reply_type_in === 2) {
    $return_arr['success'] = 'False';
    $return_arr['message'] = 'public_reply_type=2 (Public + email) is not supported via the API. Use 1 for a Public reply with no email, or 3 for an Internal note.';
    echo json_encode($return_arr);
    exit();
}

// Map the numeric reply type to the string enum stored in the DB.
if ($reply_type_in === 1) {
    $ticket_reply_type = 'Public';
} else {
    $ticket_reply_type = 'Internal';
}

$has_reply  = trim($ticket_reply) !== '';
$has_status = $status_in > 0;

// Require a ticket_id and at least one of: reply body, status change.
if (!$ticket_id) {
    $return_arr['success'] = 'False';
    $return_arr['message'] = 'ticket_id is required.';
    echo json_encode($return_arr);
    exit();
}
if (!$has_reply && !$has_status) {
    $return_arr['success'] = 'False';
    $return_arr['message'] = 'Either ticket_reply (non-empty) or status (>0) must be provided.';
    echo json_encode($return_arr);
    exit();
}

// Verify the ticket exists AND belongs to the API key's client scope.
// `$client_id` comes from validate_api_key.php and may be '%' for a global
// key — the LIKE matches both cases, same as `api/v1/tickets/read.php`.
$ticket_check = mysqli_query(
    $mysqli,
    "SELECT ticket_id, ticket_first_response_at, ticket_prefix, ticket_number, ticket_subject
     FROM tickets
     WHERE ticket_id = $ticket_id
       AND ticket_client_id LIKE '$client_id'
     LIMIT 1"
);
if (!$ticket_check || mysqli_num_rows($ticket_check) !== 1) {
    require_once '../../create_output.php';
    exit();
}
$ticket_row = mysqli_fetch_assoc($ticket_check);
$ticket_first_response_at = $ticket_row['ticket_first_response_at'];
$ticket_prefix  = sanitizeInput($ticket_row['ticket_prefix']);
$ticket_number  = intval($ticket_row['ticket_number']);
$ticket_subject = sanitizeInput($ticket_row['ticket_subject']);

// Status update (if requested). Matches the web handler: always bumps
// updated_at so the ticket sorts correctly even if the status is unchanged.
if ($status_in > 0) {
    mysqli_query(
        $mysqli,
        "UPDATE tickets SET ticket_status = $status_in, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id"
    );
    if ($status_in === 4) {
        mysqli_query(
            $mysqli,
            "UPDATE tickets SET ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id"
        );
        logAction('Ticket', 'Resolved', "API ($api_key_name) resolved ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);
    }
}

if ($has_reply) {
    // Insert the reply. ticket_reply_by = 0 mirrors `tickets/create.php`'s
    // `ticket_created_by = 0` for API-originated rows.
    $reply_escaped = mysqli_real_escape_string($mysqli, $ticket_reply);
    $insert_sql = mysqli_query(
        $mysqli,
        "INSERT INTO ticket_replies SET
            ticket_reply = '$reply_escaped',
            ticket_reply_time_worked = '$ticket_reply_time_worked',
            ticket_reply_type = '$ticket_reply_type',
            ticket_reply_by = 0,
            ticket_reply_ticket_id = $ticket_id"
    );

    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // First-response timestamp on the first public reply (matches web behavior)
        if (empty($ticket_first_response_at) && $ticket_reply_type === 'Public') {
            mysqli_query(
                $mysqli,
                "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id"
            );
        }

        logAction('Ticket', 'Reply', "API ($api_key_name) added a $ticket_reply_type reply to ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);
        logAction('API', 'Success', "Added reply to ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
    }
} else {
    // Status-only call: the UPDATE above has already run. Signal success
    // through create_output.php with insert_id = 0 — callers can detect
    // "status changed, no reply row created" by that value.
    $insert_id = 0;
    logAction('Ticket', 'Update', "API ($api_key_name) changed status of ticket $ticket_prefix$ticket_number", $client_id, $ticket_id);
    logAction('API', 'Success', "Changed status of ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
}

require_once '../../create_output.php';
