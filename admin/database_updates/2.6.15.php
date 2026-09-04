<?php

/*
 * ITFlow - Database update to version 2.6.15 (from 2.6.14)
 * Included by admin/database_updates.php - do not access directly
 *
 * Adds uptime monitor -> client/contact mapping, used by
 * cron/uptime_monitor_check.php to poll the UptimeRobot API and turn
 * down/up transitions into tickets that open once per outage and resolve
 * themselves, instead of a fresh ticket per alert email.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_uptimerobot_api_key` varchar(64) DEFAULT NULL AFTER `config_ticket_auto_assign_user_id`");

mysqli_query($mysqli, "CREATE TABLE `uptime_monitors` (
    `uptime_monitor_id` int(11) NOT NULL AUTO_INCREMENT,
    `uptime_monitor_name` varchar(200) NOT NULL COMMENT 'Exact UptimeRobot monitor friendly name',
    `uptime_monitor_provider_id` bigint(20) DEFAULT NULL COMMENT 'UptimeRobot monitor ID, learned from the API on first match',
    `uptime_monitor_client_id` int(11) NOT NULL,
    `uptime_monitor_contact_id` int(11) NOT NULL COMMENT 'Ticket contact notified when this monitor alerts',
    `uptime_monitor_last_status` tinyint(4) DEFAULT NULL COMMENT 'Last UptimeRobot status code seen (2=up, 9=down, etc)',
    `uptime_monitor_open_ticket_id` int(11) DEFAULT NULL COMMENT 'Ticket raised by the current outage, cleared once it resolves',
    `uptime_monitor_created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`uptime_monitor_id`),
    UNIQUE KEY `uptime_monitor_name` (`uptime_monitor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Structure only - which monitors map to which client/contact is install-specific data,
// applied separately after this migration runs (see the deploy notes for this change).
