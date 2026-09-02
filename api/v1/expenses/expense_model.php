<?php

// Variable assignment from POST - API create only, so guard every field with
// ?? rather than assuming the caller always sends all of them (unlike
// agent/post/expense_model.php, which backs a form that always submits every field)

$date = escapeSql($_POST['expense_date'] ?? '');
$amount = floatval($_POST['expense_amount'] ?? 0);
$account = intval($_POST['expense_account_id'] ?? 0);
$vendor = intval($_POST['expense_vendor_id'] ?? 0);
$category = intval($_POST['expense_category_id'] ?? 0);
$description = escapeSql($_POST['expense_description'] ?? '');
$reference = escapeSql($_POST['expense_reference'] ?? '');
