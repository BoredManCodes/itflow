<?php

/*
 * API - Payments - Delete
 * POST /api/v1/payments/delete.php
 *
 * Deletes a payment and re-derives the invoice status from what is left
 * (Paid becomes Sent, or Partial if other payments remain).
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   payment_id   required - Payment to delete
 *   client_id    optional - If given, must match the invoice's client
 *   note         optional - Free text reason, added to the invoice history and the notification
 *
 * Security:
 *   - The payment is found through its invoice, which is loaded through apiClientScopeSql(),
 *     so a key user cannot delete a payment on a client outside their access.
 *   - Needs Full access on both Sales and Financial, same as the UI's delete payment.
 *
 * Adds an invoice history entry, audit log entries and a notification to every agent.
 * Nothing is refunded anywhere; this only changes the books.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// $currency_format for messages - the API path never runs includes/check_login.php
require_once __DIR__ . '/../../../includes/load_company_settings.php';

// delete.php already needs Full on Sales from the resource map; the UI needs it on Financial too
if (lookupUserPermission('module_financial') < 3) {
    apiDeny("The user linked to this API key does not have permission for this action.");
}

// Parse info
$payment_id = intval($_POST['payment_id'] ?? 0);
$note = escapeSql(substr(trim((string) ($_POST['note'] ?? '')), 0, 300));

// Default
$delete_count = false;

if (!empty($payment_id)) {

    mysqli_begin_transaction($mysqli);

    // Lock the invoice row so the status recalculation sees a stable set of payments
    $payment_sql = mysqli_query(
        $mysqli,
        "SELECT payment_amount, payment_invoice_id, invoice_client_id, invoice_prefix, invoice_number, invoice_currency_code
         FROM payments
         INNER JOIN invoices ON invoice_id = payment_invoice_id
         WHERE payment_id = $payment_id
           AND 1=1 " . apiClientScopeSql('invoice_client_id') . "
         LIMIT 1
         FOR UPDATE"
    );

    $payment_row = $payment_sql ? mysqli_fetch_assoc($payment_sql) : null;

    // Ensure supplied client matches invoice client
    if ($payment_row && $client_id != 0 && intval($payment_row['invoice_client_id']) !== $client_id) {
        $payment_row = null;
    }

    $committed = false;

    if ($payment_row) {

        $invoice_id = intval($payment_row['payment_invoice_id']);
        $client_id = intval($payment_row['invoice_client_id']);
        $invoice_prefix = escapeSql($payment_row['invoice_prefix']);
        $invoice_number = intval($payment_row['invoice_number']);
        $currency_code = escapeSql($payment_row['invoice_currency_code']);
        $old_formatted = numfmt_format_currency($currency_format, floatval($payment_row['payment_amount']), $currency_code);

        $delete_sql = mysqli_query($mysqli, "DELETE FROM payments WHERE payment_id = $payment_id LIMIT 1");

        if ($delete_sql && mysqli_affected_rows($mysqli) === 1) {

            $invoice_status = updateInvoiceStatusFromPayments($invoice_id);

            $change = "Payment of $old_formatted deleted via API ($api_key_name)" . ($note !== '' ? ": $note" : "");

            mysqli_query($mysqli, "INSERT INTO history SET history_status = '$invoice_status', history_description = '$change', history_invoice_id = $invoice_id");

            $committed = mysqli_commit($mysqli);

        }

    }

    if ($committed) {

        $delete_count = 1;

        logAudit("Invoice", "Edit", "$change on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        logAudit("API", "Success", "$change on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        appNotify("Invoice Payment Deleted", "Invoice $invoice_prefix$invoice_number: $change", "/agent/invoice.php?invoice_id=$invoice_id", $client_id, $invoice_id);

    } else {

        mysqli_rollback($mysqli);

    }

}

// Output
require_once '../delete_output.php';
