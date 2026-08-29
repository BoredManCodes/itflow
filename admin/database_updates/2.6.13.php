<?php

/*
 * ITFlow - Database update to version 2.6.13 (from 2.6.12)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_ticket_auto_assign_user_id` INT(11) NOT NULL DEFAULT 0 AFTER `config_ticket_timer_autostart`");
