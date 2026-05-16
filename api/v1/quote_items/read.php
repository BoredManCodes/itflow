<?php
/*
 * API - Quote Items - Read
 * GET /api/v1/quote_items/read.php
 *
 * Returns line items belonging to quotes scoped to the API key's client.
 *
 * Parameters (GET):
 *   api_key        required  - Your API key
 *   quote_id       required* - Return items for a single quote
 *   item_id        required* - Return a single line item by its own ID
 *                  * One of quote_id or item_id must be provided
 *   limit          optional  - Max rows to return (default 50)
 *   offset         optional  - Offset for pagination (default 0)
 *
 * Security:
 *   - quote_items are always joined to quotes so that quote_client_id is
 *     checked against the API key's client scope. A scoped key can never
 *     read items belonging to another client, even when item_id is supplied
 *     directly.
 *   - $client_id is set to "%" by validate_api_key.php for All-Clients keys,
 *     which causes the LIKE to match every client, consistent with other
 *     endpoints in this API.
 */
require_once '../validate_api_key.php';
require_once '../require_get_method.php';

if (isset($_GET['item_id'])) {
    // Single line item by item_id, still JOIN to quotes to enforce client scope
    $item_id = intval($_GET['item_id']);
    $sql = mysqli_query($mysqli,
        "SELECT qi.*
         FROM quote_items qi
         INNER JOIN quotes q ON q.quote_id = qi.item_quote_id
         WHERE qi.item_id = '$item_id'
           AND q.quote_client_id LIKE '$client_id'
         LIMIT 1"
    );
} elseif (isset($_GET['quote_id'])) {
    // All items on a specific quote
    $quote_id = intval($_GET['quote_id']);
    $sql = mysqli_query($mysqli,
        "SELECT qi.*
         FROM quote_items qi
         INNER JOIN quotes q ON q.quote_id = qi.item_quote_id
         WHERE qi.item_quote_id = '$quote_id'
           AND q.quote_client_id LIKE '$client_id'
         ORDER BY qi.item_order ASC, qi.item_id ASC
         LIMIT $limit OFFSET $offset"
    );
} else {
    // No filter supplied, reject the request
    http_response_code(400);
    echo json_encode([
        'success' => 'False',
        'message' => 'A filter is required. Please supply either quote_id or item_id.',
        'count'   => 0,
        'data'    => []
    ]);
    exit;
}

// Output
require_once "../read_output.php";
