<?php

/*
 * ITFlow - Database update to version 2.7.10 (from 2.7.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_theme_custom_color` VARCHAR(7) DEFAULT NULL");
