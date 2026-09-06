<?php

/*
 * ITFlow - Database update to version 2.7.17 (from 2.7.16)
 * Included by admin/database_updates.php - do not access directly
 *
 * Square's create-payment response includes a hosted receipt_url - store it
 * so it can be surfaced back to the client instead of being discarded.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `payments`
    ADD COLUMN `payment_receipt_url` VARCHAR(500) DEFAULT NULL COMMENT 'Hosted receipt URL from the payment gateway (e.g. Square), when available'");
