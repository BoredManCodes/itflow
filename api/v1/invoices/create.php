<?php

/*
 * API - Invoices - Create
 * POST /api/v1/invoices/create.php
 *
 * Creates an empty Draft invoice. Add line items with invoice_items/create.php.
 *
 * Parameters (POST, JSON body):
 *   api_key        required - Your API key
 *   client_id      required - Client to invoice (must be within the key user's access)
 *   category_id    required - Income category ID
 *   scope          optional - Invoice scope / title (default empty)
 *   date           optional - Invoice date, YYYY-MM-DD (default today)
 *   discount       optional - Invoice discount value (default 0)
 *   discount_type  optional - 'amount' or 'percent' (default 'amount')
 *   note           optional - Invoice note
 *
 * The due date is the invoice date plus the client's net terms, same as the UI.
 * Returns the new invoice_id as insert_id; read prefix, number and url_key back
 * through invoices/read.php.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Invoice prefix / next number settings
require_once "../../../includes/load_global_settings.php";

// $session_company_currency - the API path never runs includes/check_login.php
require_once __DIR__ . '/../../../includes/load_company_settings.php';

// Parse info
$category = intval($_POST['category_id'] ?? 0);
$scope = escapeSql(substr((string) ($_POST['scope'] ?? ''), 0, 255));
$note = escapeSql((string) ($_POST['note'] ?? ''));
$discount = round(floatval($_POST['discount'] ?? 0), 2);
$discount_type = ($_POST['discount_type'] ?? '') === 'percent' ? 'percent' : 'amount';

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

if (!empty($client_id) && !empty($category) && $date_valid && $discount >= 0 && ($discount_type !== 'percent' || $discount <= 100)) {

    // Client must exist, be active and be within the key user's scope
    $client_sql = mysqli_query(
        $mysqli,
        "SELECT client_net_terms
         FROM clients
         WHERE client_id = $client_id
           AND client_archived_at IS NULL
           AND 1=1 " . apiClientScopeSql('client_id') . "
         LIMIT 1"
    );
    $client_row = $client_sql ? mysqli_fetch_assoc($client_sql) : null;

    // Category must be an active Income category
    $category_sql = mysqli_query(
        $mysqli,
        "SELECT category_id
         FROM categories
         WHERE category_id = $category
           AND category_type = 'Income'
           AND category_archived_at IS NULL
         LIMIT 1"
    );

    if ($client_row && $category_sql && mysqli_num_rows($category_sql) === 1) {

        $client_net_terms = intval($client_row['client_net_terms']);
        $config_invoice_prefix = escapeSql($config_invoice_prefix);

        // Atomically increment and get the new invoice number
        mysqli_query($mysqli, "
            UPDATE settings
            SET
                config_invoice_next_number = LAST_INSERT_ID(config_invoice_next_number),
                config_invoice_next_number = config_invoice_next_number + 1
            WHERE company_id = 1
        ");

        $invoice_number = mysqli_insert_id($mysqli);

        // Unique URL key for client access
        $url_key = randomString(32);

        // Starts at minus the discount, same as the UI; item adds recalculate the total
        $invoice_amount = 0 - calculateDiscountAmount(0, $discount, $discount_type);

        $insert_sql = mysqli_query(
            $mysqli,
            "INSERT INTO invoices SET
                invoice_prefix = '$config_invoice_prefix',
                invoice_number = $invoice_number,
                invoice_scope = '$scope',
                invoice_date = '$date',
                invoice_due = DATE_ADD('$date', INTERVAL $client_net_terms day),
                invoice_discount_amount = $discount,
                invoice_discount_type = '$discount_type',
                invoice_amount = $invoice_amount,
                invoice_currency_code = '$session_company_currency',
                invoice_note = '$note',
                invoice_category_id = $category,
                invoice_status = 'Draft',
                invoice_url_key = '$url_key',
                invoice_client_id = $client_id"
        );

        if ($insert_sql) {

            $insert_id = mysqli_insert_id($mysqli);

            mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Draft', history_description = 'Invoice created via API ($api_key_name)', history_invoice_id = $insert_id");

            logAudit("Invoice", "Create", "Created invoice $config_invoice_prefix$invoice_number - $scope via API ($api_key_name)", $client_id, $insert_id);

            logAudit("API", "Success", "Created invoice $config_invoice_prefix$invoice_number via API ($api_key_name)", $client_id, $insert_id);

            triggerCustomAction('invoice_create', $insert_id);

        }

    }

}

// Output
require_once '../create_output.php';
