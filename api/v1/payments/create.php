<?php

/*
 * API - Payments - Create
 * POST /api/v1/payments/create.php
 *
 * Records a payment against an invoice and moves the invoice to Partial or Paid.
 *
 * Parameters (POST, JSON body):
 *   api_key         required - Your API key
 *   invoice_id      required - Invoice being paid
 *   amount          required - Payment amount, greater than 0 and not more than the balance
 *   account         required - Account ID the money goes into (must not be archived)
 *   client_id       optional - If given, must match the invoice's client
 *   date            optional - Payment date, YYYY-MM-DD (default today)
 *   payment_method  optional - Free text, e.g. Cash, Card, EFTPOS
 *   reference       optional - Free text reference, e.g. terminal receipt number
 *
 * Security:
 *   - The invoice is loaded through apiClientScopeSql(), so a key user cannot pay
 *     an invoice belonging to a client outside their access.
 *   - Needs Modify access on both Sales and Financial, same as the UI.
 *   - Paid, Cancelled and Non-Billable invoices are refused.
 *   - The balance check and the insert run in one transaction with the invoice row
 *     locked, so two concurrent payments cannot overpay an invoice.
 *
 * No receipt email is sent.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// $currency_format for audit messages - the API path never runs includes/check_login.php
require_once __DIR__ . '/../../../includes/load_company_settings.php';

// The UI needs Modify on both modules to add a payment; the resource map only covers Sales
if (lookupUserPermission('module_financial') < 2) {
    apiDeny("The user linked to this API key does not have permission for this action.");
}

// Parse info
$invoice_id = intval($_POST['invoice_id'] ?? 0);
$amount = round(floatval($_POST['amount'] ?? 0), 2);
$account = intval($_POST['account'] ?? 0);
$payment_method = escapeSql(substr((string) ($_POST['payment_method'] ?? ''), 0, 200));
$reference = escapeSql(substr((string) ($_POST['reference'] ?? ''), 0, 200));

$date = date('Y-m-d');
$date_valid = true;
if (!empty($_POST['date'])) {
    $timestamp = strtotime((string) $_POST['date']);
    if ($timestamp === false) {
        $date_valid = false;
    } else {
        $date = date('Y-m-d', $timestamp);
    }
}

// Default
$insert_id = false;

if (!empty($invoice_id) && $amount > 0 && !empty($account) && $date_valid) {

    // Account must exist and be active
    $account_sql = mysqli_query(
        $mysqli,
        "SELECT account_id FROM accounts WHERE account_id = $account AND account_archived_at IS NULL LIMIT 1"
    );

    if ($account_sql && mysqli_num_rows($account_sql) === 1) {

        mysqli_begin_transaction($mysqli);

        // Lock the invoice row so the balance check and insert are atomic
        $invoice_sql = mysqli_query(
            $mysqli,
            "SELECT invoice_client_id, invoice_prefix, invoice_number, invoice_amount, invoice_status, invoice_currency_code
             FROM invoices
             WHERE invoice_id = $invoice_id
               AND invoice_archived_at IS NULL
               AND invoice_status NOT IN ('Paid', 'Cancelled', 'Non-Billable')
               AND 1=1 " . apiClientScopeSql('invoice_client_id') . "
             LIMIT 1
             FOR UPDATE"
        );

        $invoice_row = $invoice_sql ? mysqli_fetch_assoc($invoice_sql) : null;

        // Ensure supplied client matches invoice client
        if ($invoice_row && $client_id != 0 && intval($invoice_row['invoice_client_id']) !== $client_id) {
            $invoice_row = null;
        }

        $committed = false;

        if ($invoice_row) {

            $client_id = intval($invoice_row['invoice_client_id']);
            $invoice_prefix = escapeSql($invoice_row['invoice_prefix']);
            $invoice_number = intval($invoice_row['invoice_number']);
            $invoice_amount = floatval($invoice_row['invoice_amount']);
            $currency_code = escapeSql($invoice_row['invoice_currency_code']);

            $paid_sql = mysqli_query($mysqli, "SELECT COALESCE(SUM(payment_amount), 0) AS paid FROM payments WHERE payment_invoice_id = $invoice_id");
            $paid_row = mysqli_fetch_assoc($paid_sql);
            $balance = round($invoice_amount - floatval($paid_row['paid']), 2);

            // Same rule as the UI: a payment can not be more than the balance
            if ($amount <= $balance) {

                $insert_sql = mysqli_query(
                    $mysqli,
                    "INSERT INTO payments SET
                        payment_date = '$date',
                        payment_amount = $amount,
                        payment_currency_code = '$currency_code',
                        payment_account_id = $account,
                        payment_method = '$payment_method',
                        payment_reference = '$reference',
                        payment_invoice_id = $invoice_id"
                );

                if ($insert_sql) {

                    $insert_id = mysqli_insert_id($mysqli);

                    $invoice_status = round($balance - $amount, 2) == 0 ? 'Paid' : 'Partial';

                    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = '$invoice_status' WHERE invoice_id = $invoice_id");

                    mysqli_query($mysqli, "INSERT INTO history SET history_status = '$invoice_status', history_description = 'Payment added via API ($api_key_name)', history_invoice_id = $invoice_id");

                    $committed = mysqli_commit($mysqli);

                }

            }

        }

        if ($committed) {

            $amount_formatted = numfmt_format_currency($currency_format, $amount, $currency_code);

            logAudit("Invoice", "Payment", "Payment amount of $amount_formatted added to invoice $invoice_prefix$invoice_number via API ($api_key_name)", $client_id, $invoice_id);

            logAudit("API", "Success", "Added payment of $amount_formatted to invoice $invoice_prefix$invoice_number via API ($api_key_name)", $client_id, $invoice_id);

            triggerCustomAction('invoice_pay', $invoice_id);

        } else {

            mysqli_rollback($mysqli);
            $insert_id = false;

        }

    }

}

// Output
require_once '../create_output.php';
