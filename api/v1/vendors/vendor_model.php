<?php

// Variable assignment from POST - API create only, so every field beyond
// vendor_name is optional (unlike the agent form, which always submits all of them)

$name = escapeSql($_POST['vendor_name'] ?? '');
$description = escapeSql($_POST['vendor_description'] ?? '');
$contact_name = escapeSql($_POST['vendor_contact_name'] ?? '');
$phone = preg_replace("/[^0-9]/", '', $_POST['vendor_phone'] ?? '');
$email = escapeSql($_POST['vendor_email'] ?? '');
$website = preg_replace("(^https?://)", "", escapeSql($_POST['vendor_website'] ?? ''));
$account_number = escapeSql($_POST['vendor_account_number'] ?? '');
$notes = escapeSql($_POST['vendor_notes'] ?? '');
