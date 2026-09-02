<?php

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

// Specific account via its ID (single)
if (isset($_GET['account_id'])) {
    $id = intval($_GET['account_id']);
    $sql = mysqli_query($mysqli, "SELECT * FROM accounts WHERE account_id = '$id'");

} else {
    // All accounts
    $sql = mysqli_query($mysqli, "SELECT * FROM accounts ORDER BY account_id LIMIT $limit OFFSET $offset");
}

// Output
require_once "../read_output.php";
