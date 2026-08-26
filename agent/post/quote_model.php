<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$date = escapeSql($_POST['date']);
$expire = escapeSql($_POST['expire']);
$category = intval($_POST['category']);
$scope = escapeSql($_POST['scope']);
$quote_discount = floatval($_POST['quote_discount']);
$quote_discount_type = ($_POST['quote_discount_type'] ?? '') === 'percent' ? 'percent' : 'amount';

$config_quote_prefix = escapeSql($config_quote_prefix);
