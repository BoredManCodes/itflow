<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';


// Specific ticket via ID (single)
if (isset($_GET['ticket_id'])) {
    $id = intval($_GET['ticket_id']);
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_id = '$id' AND 1=1 " . apiClientScopeSql('ticket_client_id') . ""
    );

} else {
    // All tickets (by client ID if given, or all in general if key permits)
    $client_sql = '';
    if (isset($_GET['client_id'])) {
        $client_id = intval($_GET['client_id']);
        $client_sql = " AND ticket_client_id = '$client_id'";
    }

    // Newest first: callers asking "what's the status of my ticket" want the
    // recent ones, and an ascending page of 50 hides everything raised since.
    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE 1=1 " . apiClientScopeSql('ticket_client_id') . "$client_sql
        ORDER BY ticket_id DESC LIMIT $limit OFFSET $offset"
    );
}

// Output
require_once "../read_output.php";

