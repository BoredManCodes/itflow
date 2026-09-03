<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse info
require_once 'vendor_model.php';

// Default
$insert_id = false;

if (!empty($name)) {

    // Global (org-level) vendor - agent/modals/expense/expense_add.php's vendor
    // picker only lists vendor_client_id = 0, so that's what an expense-filing
    // tool needs to be able to select the vendor it just created
    $insert_sql = mysqli_query($mysqli, "INSERT INTO vendors SET vendor_name = '$name', vendor_description = '$description', vendor_contact_name = '$contact_name', vendor_phone = '$phone', vendor_email = '$email', vendor_website = '$website', vendor_account_number = '$account_number', vendor_notes = '$notes', vendor_client_id = 0");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Logging
        logAudit("Vendor", "Create", "$name via API ($api_key_name)", 0, $insert_id);
        logAudit("API", "Success", "Created vendor $name via API ($api_key_name)", 0);
    }

}

// Output
require_once '../create_output.php';
