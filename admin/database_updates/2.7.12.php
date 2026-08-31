<?php

/*
 * ITFlow - Database update to version 2.7.12 (from 2.7.11)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_phone_show_country_code` tinyint(1) NOT NULL DEFAULT 1 AFTER `config_timezone`,
    ADD `config_phone_mask` varchar(30) NOT NULL DEFAULT '' AFTER `config_phone_show_country_code`");
