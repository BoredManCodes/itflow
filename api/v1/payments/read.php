<?php

/*
 * API - Payments - Read
 * GET /api/v1/payments/read.php
 *
 * Parameters (GET):
 *   api_key      required - Your API key
 *   payment_id   optional - Return a single payment
 *   invoice_id   optional - Return every payment on one invoice
 *   client_id    optional - Only payments on invoices for this client
 *   limit        optional - Max rows to return (default 50)
 *   offset       optional - Offset for pagination (default 0)
 *
 * Security:
 *   Payments have no client column, so they are always joined to their invoice and
 *   the invoice's client is checked against the key user's scope.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

if (isset($_GET['payment_id'])) {
    // Single payment
    $id = intval($_GET['payment_id']);
    $sql = mysqli_query($mysqli,
        "SELECT p.*
         FROM payments p
         INNER JOIN invoices i ON i.invoice_id = p.payment_invoice_id
         WHERE p.payment_id = $id
           AND 1=1 " . apiClientScopeSql('i.invoice_client_id') . "
         LIMIT 1"
    );

} elseif (isset($_GET['invoice_id'])) {
    // All payments on an invoice
    $id = intval($_GET['invoice_id']);
    $sql = mysqli_query($mysqli,
        "SELECT p.*
         FROM payments p
         INNER JOIN invoices i ON i.invoice_id = p.payment_invoice_id
         WHERE p.payment_invoice_id = $id
           AND 1=1 " . apiClientScopeSql('i.invoice_client_id') . "
         ORDER BY p.payment_id
         LIMIT $limit OFFSET $offset"
    );

} else {
    // All payments
    $sql = mysqli_query($mysqli,
        "SELECT p.*
         FROM payments p
         INNER JOIN invoices i ON i.invoice_id = p.payment_invoice_id
         WHERE 1=1 " . apiClientScopeSql('i.invoice_client_id') . "
         ORDER BY p.payment_id
         LIMIT $limit OFFSET $offset"
    );
}

// Output
require_once "../read_output.php";
