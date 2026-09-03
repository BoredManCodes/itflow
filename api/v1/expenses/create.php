<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// $session_company_currency - the API path never runs includes/check_login.php
// (that's session-based UI bootstrap), so pull it directly the same way that does
require_once __DIR__ . '/../../../includes/load_company_settings.php';

// Parse info
require_once 'expense_model.php';

// Default
$insert_id = false;

if (!empty($date) && $amount != 0 && !empty($account)) {

    $insert_sql = mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$session_company_currency', expense_account_id = $account, expense_vendor_id = $vendor, expense_client_id = $client_id, expense_category_id = $category, expense_description = '$description', expense_reference = '$reference'");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Optional receipt - see validate_api_key.php: this API only ever sees a
        // JSON body, so there's no $_FILES upload path here, only base64-in-JSON
        if (!empty($_POST['expense_receipt_base64']) && !empty($_POST['expense_receipt_filename'])) {
            $new_file_name = saveBase64File(
                $_POST['expense_receipt_base64'],
                $_POST['expense_receipt_filename'],
                $_SERVER['DOCUMENT_ROOT'] . '/uploads/expenses/',
                ['jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf']
            );
            if ($new_file_name) {
                mysqli_query($mysqli, "UPDATE expenses SET expense_receipt = '$new_file_name' WHERE expense_id = $insert_id");
            }
        }

        // Logging
        logAudit("Expense", "Create", "$description via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created expense $description via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
