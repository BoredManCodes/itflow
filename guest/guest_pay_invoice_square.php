<?php

require_once 'includes/inc_all_guest.php';
require_once '../includes/square_api.php';

DEFINE("WORDING_PAYMENT_FAILED", "<br><h2>There was an error verifying your payment. Please contact us for more information before attempting payment again.</h2>");

// --- Get Square config from payment_providers table ---
$square_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT payment_provider_account, payment_provider_id, payment_provider_location_id, payment_provider_private_key, payment_provider_public_key FROM payment_providers WHERE payment_provider_name = 'Square' LIMIT 1"));

if (!$square_provider) {
    exit("Square is not enabled / configured");
}

$square_provider_id  = intval($square_provider['payment_provider_id']);
$square_application_id = escapeHtml($square_provider['payment_provider_public_key']);
$square_location_id  = escapeHtml($square_provider['payment_provider_location_id']);
$square_access_token = $square_provider['payment_provider_private_key'];
$square_account      = intval($square_provider['payment_provider_account']);
$square_sandbox      = squareIsSandbox($square_application_id);

// Process a submitted card token
if (isset($_POST['invoice_id'], $_POST['url_key'], $_POST['source_id'])) {

    $invoice_id      = intval($_POST['invoice_id']);
    $invoice_url_key = escapeSql($_POST['url_key']);
    $source_id       = escapeSql($_POST['source_id']);

    // Get/Check invoice (& client/primary contact)
    $invoice_sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, contact_email, contact_name, invoice_amount, invoice_currency_code,
            invoice_id, invoice_number, invoice_prefix, invoice_url_key FROM invoices
         LEFT JOIN clients ON invoice_client_id = client_id
         LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
         WHERE invoice_id = $invoice_id
         AND invoice_url_key = '$invoice_url_key'
         AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')
         LIMIT 1"
    );
    if (!$invoice_sql || mysqli_num_rows($invoice_sql) !== 1) {
        error_log("Square payment error - Invoice with ID $invoice_id not found or not eligible.");
        exit(WORDING_PAYMENT_FAILED);
    }

    $row = mysqli_fetch_assoc($invoice_sql);
    $invoice_id            = intval($row['invoice_id']);
    $invoice_prefix        = escapeSql($row['invoice_prefix']);
    $invoice_number        = intval($row['invoice_number']);
    $invoice_amount        = floatval($row['invoice_amount']);
    $invoice_currency_code = strtoupper(escapeSql($row['invoice_currency_code']));
    $invoice_url_key       = escapeSql($row['invoice_url_key']);
    $client_id             = intval($row['client_id']);
    $client_name           = escapeSql($row['client_name']);
    $contact_name          = escapeSql($row['contact_name']);
    $contact_email         = escapeSql($row['contact_email']);

    $sql_amount_paid_previously = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $amount_paid_previously = floatval(mysqli_fetch_assoc($sql_amount_paid_previously)['amount_paid']);
    $balance_to_pay = round($invoice_amount - $amount_paid_previously, 2);

    if ((int) round($balance_to_pay * 100) <= 0) {
        error_log("Square payment error - Invoice $invoice_id has no balance outstanding.");
        exit(WORDING_PAYMENT_FAILED);
    }

    // Create the payment against Square
    try {
        $square_response = squareApiRequest('POST', '/v2/payments', $square_access_token, $square_sandbox, [
            'idempotency_key' => bin2hex(random_bytes(16)),
            'source_id' => $source_id,
            'amount_money' => [
                'amount' => (int) round($balance_to_pay * 100),
                'currency' => $invoice_currency_code,
            ],
            'location_id' => $square_location_id,
            'note' => "ITFlow: Invoice $invoice_prefix$invoice_number",
            'reference_id' => (string) $invoice_id,
        ]);
    } catch (\Throwable $e) {
        error_log("Square payment error - encountered exception during payment create for invoice ID $invoice_id / $invoice_prefix$invoice_number: " . $e->getMessage());
        logApp("Square", "error", "Exception during payment create for invoice ID $invoice_id: " . $e->getMessage());
        exit(WORDING_PAYMENT_FAILED);
    }

    $square_payment = $square_response['payment'] ?? null;

    if (!$square_payment || $square_payment['status'] !== 'COMPLETED') {
        $status = $square_payment['status'] ?? 'UNKNOWN';
        error_log("Square payment error - payment for invoice $invoice_id did not complete, status $status");
        logApp("Square", "error", "Payment for invoice ID $invoice_id did not complete, status $status");
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Payment failed', history_description = 'Square pay failed due to payment error', history_invoice_id = $invoice_id");
        exit(WORDING_PAYMENT_FAILED);
    }

    $square_payment_id = escapeSql($square_payment['id']);
    $pi_amount_paid = floatval($square_payment['amount_money']['amount'] / 100);
    $pi_currency = strtoupper(escapeSql($square_payment['amount_money']['currency']));

    // Compare in whole cents
    if ((int) round($balance_to_pay * 100) !== (int) round($pi_amount_paid * 100)) {
        error_log("Square payment error - Invoice balance does not match amount paid for $square_payment_id");
        exit(WORDING_PAYMENT_FAILED);
    }

    $pi_date = date('Y-m-d');

    // Claim the invoice - see guest_pay_invoice_stripe.php for why this conditional
    // UPDATE is the concurrency guard rather than a plain check-then-write.
    mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Paid' WHERE invoice_id = $invoice_id AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')");
    if (mysqli_affected_rows($mysqli) !== 1) {
        error_log("Square payment - invoice $invoice_id was already settled by a concurrent request; skipping duplicate booking of $square_payment_id");
        header('Location: //' . $config_base_url . '/guest/guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . $invoice_url_key);
        exit();
    }

    // Add Payment to History
    mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$pi_date', payment_amount = $pi_amount_paid, payment_currency_code = '$pi_currency', payment_account_id = $square_account, payment_method = 'Square', payment_reference = 'Square - $square_payment_id', payment_invoice_id = $invoice_id");

    mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Paid', history_description = 'Online Payment added (client) - $ip - $os - $browser', history_invoice_id = $invoice_id");

    // Notify
    appNotify("Invoice Paid", "Invoice $invoice_prefix$invoice_number has been paid by $client_name - $ip - $os - $browser", "/agent/invoice.php?invoice_id=$invoice_id", $client_id);

    triggerCustomAction('invoice_pay', $invoice_id);

    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Payment', log_action = 'Create', log_description = 'Square payment of $pi_currency $pi_amount_paid against invoice $invoice_prefix$invoice_number - $square_payment_id', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $client_id");

    // Email Receipt
    $sql_company = mysqli_query($mysqli, "SELECT company_locale, company_name, company_phone FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql_company);
    $company_name = escapeSql($row['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone']));
    $company_locale = escapeSql($row['company_locale']);

    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    $sql_settings = mysqli_query($mysqli, "SELECT config_invoice_from_email, config_invoice_from_name,
        config_invoice_paid_notification_email, config_smtp_host FROM settings WHERE company_id = 1");
    $settings = mysqli_fetch_assoc($sql_settings);

    $config_smtp_host = $settings['config_smtp_host'];
    $config_invoice_from_name = escapeSql($settings['config_invoice_from_name']);
    $config_invoice_from_email = escapeSql($settings['config_invoice_from_email']);
    $config_invoice_paid_notification_email = escapeSql($settings['config_invoice_paid_notification_email']);

    if (!empty($config_smtp_host)) {
        $rendered = renderEmailTemplate('payment_received_online', [
            'contact_name' => $contact_name,
            'amount' => numfmt_format_currency($currency_format, $pi_amount_paid, $invoice_currency_code),
            'invoice_url' => "https://$config_base_url/guest/guest_view_invoice.php?invoice_id=$invoice_id&url_key=$invoice_url_key",
            'invoice_prefix' => $invoice_prefix,
            'invoice_number' => $invoice_number,
            'company_name' => $company_name,
            'company_phone' => $company_phone,
            'from_email' => $config_invoice_from_email,
        ]);
        $subject = $rendered['subject'];
        $body = $rendered['body'];

        $data = [
            [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $contact_email,
                'recipient_name' => $contact_name,
                'subject' => $subject,
                'body' => $body,
            ]
        ];
        if (!empty($config_invoice_paid_notification_email)) {
            $rendered_internal = renderEmailTemplate('payment_received_internal', [
                'app_name' => $config_app_name,
                'client_name' => $client_name,
                'invoice_prefix' => $invoice_prefix,
                'invoice_number' => $invoice_number,
                'client_receipt_body' => $body,
            ]);
            $data[] = [
                'from' => $config_invoice_from_email,
                'from_name' => $config_invoice_from_name,
                'recipient' => $config_invoice_paid_notification_email,
                'recipient_name' => $contact_name,
                'subject' => $rendered_internal['subject'],
                'body' => $rendered_internal['body'],
            ];
        }
        addToMailQueue($data);
        mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Sent', history_description = 'Emailed Receipt!', history_invoice_id = $invoice_id");
    }

    header('Location: //' . $config_base_url . '/guest/guest_view_invoice.php?invoice_id=' . $invoice_id . '&url_key=' . $invoice_url_key);
    exit();

// Show payment form
} elseif (isset($_GET['invoice_id'], $_GET['url_key'])) {

    $invoice_url_key = escapeSql($_GET['url_key']);
    $invoice_id      = intval($_GET['invoice_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT client_id, client_name, invoice_amount, invoice_currency_code, invoice_date,
            invoice_discount_amount, invoice_discount_type, invoice_due, invoice_id, invoice_number,
            invoice_prefix, invoice_status FROM invoices
         LEFT JOIN clients ON invoice_client_id = client_id
         WHERE invoice_id = $invoice_id
         AND invoice_url_key = '$invoice_url_key'
         AND invoice_status NOT IN ('Draft', 'Paid', 'Cancelled')
         LIMIT 1"
    );

    if (!$sql || mysqli_num_rows($sql) !== 1) {
        echo "<br><h2>Oops, something went wrong! Please ensure you have the correct URL and have not already paid this invoice.</h2>";
        require_once 'includes/guest_footer.php';
        error_log("Square payment error - Invoice with ID $invoice_id not found or not eligible.");
        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $invoice_id            = intval($row['invoice_id']);
    $invoice_prefix        = escapeHtml($row['invoice_prefix']);
    $invoice_number        = intval($row['invoice_number']);
    $invoice_discount      = floatval($row['invoice_discount_amount']);
    $invoice_discount_type = $row['invoice_discount_type'] === 'percent' ? 'percent' : 'amount';
    $invoice_amount        = floatval($row['invoice_amount']);
    $invoice_currency_code = escapeHtml($row['invoice_currency_code']);

    $sql_company = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $company_row = mysqli_fetch_assoc($sql_company);
    $company_locale = escapeHtml($company_row['company_locale']);

    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $amount_paid = floatval(mysqli_fetch_assoc($sql_amount_paid)['amount_paid']);
    $balance_to_pay = round($invoice_amount - $amount_paid, 2);

    $sql_invoice_items = mysqli_query($mysqli, "SELECT item_name, item_quantity, item_total FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_id ASC");

    $currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

    $square_js_host = $square_sandbox ? 'sandbox.web.squarecdn.com' : 'web.squarecdn.com';

    ?>

    <script src="https://<?= $square_js_host ?>/v1/square.js"></script>

    <div class="row py-5">
        <div class="col-sm">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payment for Invoice: <strong><?= "$invoice_prefix$invoice_number" ?></strong></h3>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $items_total = 0;
                        while ($row = mysqli_fetch_assoc($sql_invoice_items)) {
                            $item_name = escapeHtml($row['item_name']);
                            $item_quantity = floatval($row['item_quantity']);
                            $item_total = floatval($row['item_total']);
                            $items_total += $item_total;
                        ?>
                            <tr>
                                <td><?= $item_name ?></td>
                                <td class="text-center"><?= $item_quantity ?></td>
                                <td class="text-end"><?= numfmt_format_currency($currency_format, $item_total, $invoice_currency_code) ?></td>
                            </tr>
                        <?php } ?>
                        <?php if ($invoice_discount > 0) {
                            $discount_display_amount = calculateDiscountAmount($items_total, $invoice_discount, $invoice_discount_type);
                        ?>
                            <tr class="text-end">
                                <td colspan="2">Discount<?php if ($invoice_discount_type === 'percent') {
                                                            echo ' (' . rtrim(rtrim(number_format($invoice_discount, 2), '0'), '.') . '%)';
                                                        } ?></td>

                                <td>
                                    <?= numfmt_format_currency($currency_format, $discount_display_amount, $invoice_currency_code) ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (intval($amount_paid) > 0) { ?>
                            <tr class="text-end">
                                <td colspan="2">Paid</td>
                                <td>
                                    <?= numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code) ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-sm offset-sm-1">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payment Total: <strong><?= numfmt_format_currency($currency_format, $balance_to_pay, $invoice_currency_code) ?></strong></h3>
                </div>
                <div class="card-body">
                    <form id="payment-form" method="post">
                        <input type="hidden" id="square_application_id" value="<?= $square_application_id ?>">
                        <input type="hidden" id="square_location_id" value="<?= $square_location_id ?>">
                        <input type="hidden" id="invoice_id" name="invoice_id" value="<?= $invoice_id ?>">
                        <input type="hidden" id="url_key" name="url_key" value="<?= $invoice_url_key ?>">
                        <input type="hidden" id="source_id" name="source_id" value="">
                        <div id="card-container"></div>
                        <br>
                        <button type="submit" id="submit" class="btn btn-primary btn-lg w-100 text-bold" hidden="hidden">
                            <div class="spinner hidden" id="spinner"></div>
                            <span id="button-text"><i class="fas fa-check me-2"></i>Pay Invoice</span>
                        </button>
                        <div id="payment-message" class="d-none"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/guest_pay_invoice_square.js"></script>

    <?php

} else {
    exit(WORDING_PAYMENT_FAILED);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
