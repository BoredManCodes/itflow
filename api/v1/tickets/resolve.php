<?php

// Resolve endpoint for tickets
// Just send a POST here with a ticket & client id, and we do the rest

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id']);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    // Resolving a ticket by ID does not require the caller to already know
    // which client owns it (unlike create, which names a client to act on) -
    // look the ticket up first, then check access against its real client_id,
    // rather than requiring $client_id to have been supplied up front.
    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_first_response_at, ticket_id, ticket_number, ticket_prefix, ticket_client_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_resolved_at IS NULL LIMIT 1"));

    if ($ticket_row && apiUserCanAccessClient($ticket_row['ticket_client_id'])) {

        // Grab what we need, not using the model
        $ticket_id = intval($ticket_row['ticket_id']); // Override so things fail if this is bad
        $ticket_prefix = escapeSql($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_first_response_at = escapeSql($ticket_row['ticket_first_response_at']);
        $ticket_client_id = intval($ticket_row['ticket_client_id']);

        // Mark FR (if not)
        if (empty($ticket_first_response_at)) {
            setTicketFirstResponse($ticket_id);
        }

        // Resolve
        $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $ticket_client_id LIMIT 1");
        syncTicketSlaClock($ticket_id);
        setTicketResolutionSlaMet($ticket_id);

        // Check insert & get insert ID
        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logTicketHistory($ticket_id, "Resolved via the API ($api_key_name)");

            logAudit("Ticket", "Resolved", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $ticket_client_id, $ticket_id);
            logAudit("API", "Success", "Resolved ticket $ticket_prefix$ticket_number via API ($api_key_name)", $ticket_client_id);
        }

        triggerCustomAction('ticket_resolve', $ticket_id);

    } else {
        $ticket_id = 0; // Not found, already resolved, or outside this key's client scope
    }

}

// Output
require_once '../update_output.php';