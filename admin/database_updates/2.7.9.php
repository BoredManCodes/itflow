<?php

/*
 * ITFlow - Database update to version 2.7.9 (from 2.7.8)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD COLUMN IF NOT EXISTS `config_update_notification_email` VARCHAR(200) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `config_update_last_notified_version` VARCHAR(40) DEFAULT NULL");
