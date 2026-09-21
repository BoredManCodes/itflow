<?php

/*
 * API - Payments - Update
 * POST /api/v1/payments/update.php
 *
 * Changes the amount of an existing payment and re-derives the invoice status
 * (a Paid invoice whose payment is reduced becomes Partial, and the difference
 * shows as outstanding). Everything else on the payment is left alone.
 *
 * Parameters (POST, JSON body):
 *   api_key      required - Your API key
 *   payment_id   required - Payment to change
 *   amount       required - New amount, greater than 0; the invoice's payments may not exceed its total
 *   client_id    optional - If given, must match the invoice's client
 *   note         optional - Free text reason, added to the invoice history and the notification
 *
 * Security:
 *   - The payment is found through its invoice, which is loaded through apiClientScopeSql(),
 *     so a key user cannot touch a payment on a client outside their access.
 *   - Needs Full access on both Sales and Financial, same as the UI's edit payment.
 *   - The check and the update run in one transaction with the invoice row locked.
 *
 * Adds an invoice history entry, audit log entries and a notification to every agent.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// $currency_format for messages - the API path never runs includes/check_login.php
require_once __DIR__ . '/../../../includes/load_company_settings.php';

// The UI needs Full on both modules to edit a payment; the resource map only gives Sales Modify here
if (lookupUserPermission('module_sales') < 3 || lookupUserPermission('module_financial') < 3) {
    apiDeny("The user linked to this API key does not have permission for this action.");
}

// Parse info
$payment_id = intval($_POST['payment_id'] ?? 0);
$amount = round(floatval($_POST['amount'] ?? 0), 2);
$note = escapeSql(substr(trim((string) ($_POST['note'] ?? '')), 0, 300));

// Default
$update_count = false;

if (!empty($payment_id) && $amount > 0) {

    mysqli_begin_transaction($mysqli);

    // Lock the invoice row so the overpayment check and the update are atomic
    $payment_sql = mysqli_query(
        $mysqli,
        "SELECT payment_amount, payment_invoice_id, invoice_client_id, invoice_prefix, invoice_number, invoice_amount, invoice_currency_code
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
        $invoice_amount = floatval($payment_row['invoice_amount']);
        $currency_code = escapeSql($payment_row['invoice_currency_code']);
        $old_amount = floatval($payment_row['payment_amount']);

        $others_sql = mysqli_query($mysqli, "SELECT COALESCE(SUM(payment_amount), 0) AS paid FROM payments WHERE payment_invoice_id = $invoice_id AND payment_id != $payment_id");
        $others_row = mysqli_fetch_assoc($others_sql);

        // Same rule as create: payments can not add up to more than the invoice
        if (round(floatval($others_row['paid']) + $amount, 2) <= round($invoice_amount, 2)) {

            $update_sql = mysqli_query($mysqli, "UPDATE payments SET payment_amount = $amount WHERE payment_id = $payment_id LIMIT 1");

            if ($update_sql) {

                $invoice_status = updateInvoiceStatusFromPayments($invoice_id);

                $old_formatted = numfmt_format_currency($currency_format, $old_amount, $currency_code);
                $new_formatted = numfmt_format_currency($currency_format, $amount, $currency_code);
                $change = "Payment amount changed from $old_formatted to $new_formatted via API ($api_key_name)" . ($note !== '' ? ": $note" : "");

                mysqli_query($mysqli, "INSERT INTO history SET history_status = '$invoice_status', history_description = '$change', history_invoice_id = $invoice_id");

                $committed = mysqli_commit($mysqli);

            }

        }

    }

    if ($committed) {

        $update_count = 1;

        logAudit("Invoice", "Edit", "$change on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        logAudit("API", "Success", "$change on Invoice $invoice_prefix$invoice_number", $client_id, $invoice_id);

        appNotify("Invoice Payment Changed", "Invoice $invoice_prefix$invoice_number: $change", "/agent/invoice.php?invoice_id=$invoice_id", $client_id, $invoice_id);

    } else {

        mysqli_rollback($mysqli);

    }

}

// Output
require_once '../update_output.php';
