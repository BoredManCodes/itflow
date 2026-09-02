<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Specific category via its ID (single)
if (isset($_GET['category_id'])) {
    $id = intval($_GET['category_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_id = '$id'");

} else {
    // All categories, optionally filtered by type (defaults to Expense - see
    // agent/modals/expense/expense_add.php's category picker for the same filter)
    $type = isset($_GET['category_type']) ? escapeSql($_GET['category_type']) : 'Expense';
    $sql = mysqli_query($mysqli, "SELECT * FROM categories WHERE category_type = '$type' AND category_archived_at IS NULL ORDER BY category_name LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
