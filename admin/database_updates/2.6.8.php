<?php

/*
 * ITFlow - Database update to version 2.6.8 (from 2.6.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_update_notification_email` VARCHAR(200) DEFAULT NULL,
    ADD `config_update_last_notified_version` VARCHAR(40) DEFAULT NULL");
