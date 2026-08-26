<?php

/*
 * ITFlow - Database update to version 2.6.12 (from 2.6.11)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_tax_id_label` VARCHAR(50) NOT NULL DEFAULT 'Tax ID' AFTER `config_invoice_show_tax_id`");
