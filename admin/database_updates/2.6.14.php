<?php

/*
 * ITFlow - Database update to version 2.6.14 (from 2.6.13)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE `ticket_rules` (
    `ticket_rule_id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_rule_name` varchar(200) NOT NULL,
    `ticket_rule_match_type` enum('all','any') NOT NULL DEFAULT 'all',
    `ticket_rule_stop_processing` tinyint(1) NOT NULL DEFAULT 1,
    `ticket_rule_order` int(11) NOT NULL DEFAULT 0,
    `ticket_rule_active` tinyint(1) NOT NULL DEFAULT 1,
    `ticket_rule_created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `ticket_rule_archived_at` datetime DEFAULT NULL,
    PRIMARY KEY (`ticket_rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

mysqli_query($mysqli, "CREATE TABLE `ticket_rule_conditions` (
    `ticket_rule_condition_id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_rule_condition_rule_id` int(11) NOT NULL,
    `ticket_rule_condition_field` varchar(50) NOT NULL,
    `ticket_rule_condition_operator` varchar(20) NOT NULL,
    `ticket_rule_condition_value` varchar(500) NOT NULL,
    PRIMARY KEY (`ticket_rule_condition_id`),
    KEY `ticket_rule_condition_rule_id` (`ticket_rule_condition_rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

mysqli_query($mysqli, "CREATE TABLE `ticket_rule_actions` (
    `ticket_rule_action_id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_rule_action_rule_id` int(11) NOT NULL,
    `ticket_rule_action_type` varchar(50) NOT NULL,
    `ticket_rule_action_value` varchar(500) NOT NULL,
    PRIMARY KEY (`ticket_rule_action_id`),
    KEY `ticket_rule_action_rule_id` (`ticket_rule_action_rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
