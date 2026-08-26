<?php

/*
 * ITFlow - Database update to version 2.6.11 (from 2.6.10)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `invoices`
    ADD `invoice_discount_type` ENUM('amount','percent') NOT NULL DEFAULT 'amount' AFTER `invoice_discount_amount`");

mysqli_query($mysqli, "ALTER TABLE `quotes`
    ADD `quote_discount_type` ENUM('amount','percent') NOT NULL DEFAULT 'amount' AFTER `quote_discount_amount`");

mysqli_query($mysqli, "ALTER TABLE `recurring_invoices`
    ADD `recurring_invoice_discount_type` ENUM('amount','percent') NOT NULL DEFAULT 'amount' AFTER `recurring_invoice_discount_amount`");
