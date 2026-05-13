<?php

require_once '../../validate_api_key.php';

require_once '../../require_get_method.php';

// Specific reply via reply_id (single)
if (isset($_GET['reply_id'])) {
    $reply_id = intval($_GET['reply_id']);
    $sql = mysqli_query(
        $mysqli,
        "SELECT ticket_replies.*, users.user_name AS ticket_reply_by_name
        FROM ticket_replies
        LEFT JOIN tickets ON ticket_reply_ticket_id = ticket_id
        LEFT JOIN users ON ticket_reply_by = user_id
        WHERE ticket_reply_id = $reply_id
          AND ticket_client_id LIKE '$client_id'"
    );

// All replies for a given ticket
} elseif (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);
    $sql = mysqli_query(
        $mysqli,
        "SELECT ticket_replies.*, users.user_name AS ticket_reply_by_name
        FROM ticket_replies
        LEFT JOIN tickets ON ticket_reply_ticket_id = ticket_id
        LEFT JOIN users ON ticket_reply_by = user_id
        WHERE ticket_reply_ticket_id = $ticket_id
          AND ticket_client_id LIKE '$client_id'
        ORDER BY ticket_reply_id DESC
        LIMIT $limit OFFSET $offset"
    );

// All replies the caller can see (scoped by client_id from the API key)
} else {
    $sql = mysqli_query(
        $mysqli,
        "SELECT ticket_replies.*, users.user_name AS ticket_reply_by_name
        FROM ticket_replies
        LEFT JOIN tickets ON ticket_reply_ticket_id = ticket_id
        LEFT JOIN users ON ticket_reply_by = user_id
        WHERE ticket_client_id LIKE '$client_id'
        ORDER BY ticket_reply_id DESC
        LIMIT $limit OFFSET $offset"
    );
}

// Output
require_once '../../read_output.php';
