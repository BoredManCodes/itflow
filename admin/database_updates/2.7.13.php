<?php

/*
 * ITFlow - Database update to version 2.7.13 (from 2.7.12)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `payment_providers`
    ADD `payment_provider_location_id` varchar(200) DEFAULT NULL AFTER `payment_provider_public_key`");
