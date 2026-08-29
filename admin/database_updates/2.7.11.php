<?php

/*
 * ITFlow - Database update to version 2.7.11 (from 2.7.10)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `email_templates` (
    `email_template_id` int(11) NOT NULL AUTO_INCREMENT,
    `email_template_key` varchar(100) NOT NULL,
    `email_template_name` varchar(150) NOT NULL,
    `email_template_subject` varchar(255) NOT NULL,
    `email_template_body` mediumtext NOT NULL,
    `email_template_tokens` text DEFAULT NULL,
    `email_template_updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`email_template_id`),
    UNIQUE KEY `email_template_key` (`email_template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
