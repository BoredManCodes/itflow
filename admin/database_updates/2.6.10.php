<?php

/*
 * ITFlow - Database update to version 2.6.10 (from 2.6.9)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `settings`
    ADD `config_vapid_public_key` VARCHAR(255) DEFAULT NULL,
    ADD `config_vapid_private_key` VARCHAR(255) DEFAULT NULL,
    ADD `config_vapid_subject` VARCHAR(255) DEFAULT NULL");

mysqli_query($mysqli, "CREATE TABLE `push_subscriptions` (
    `push_subscription_id` int(11) NOT NULL AUTO_INCREMENT,
    `push_subscription_user_id` int(11) NOT NULL,
    `push_subscription_endpoint` varchar(512) NOT NULL,
    `push_subscription_endpoint_hash` char(40) NOT NULL,
    `push_subscription_p256dh` varchar(255) NOT NULL,
    `push_subscription_auth` varchar(255) NOT NULL,
    `push_subscription_user_agent` varchar(255) DEFAULT NULL,
    `push_subscription_created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`push_subscription_id`),
    UNIQUE KEY `push_subscription_endpoint_hash` (`push_subscription_endpoint_hash`),
    KEY `push_subscription_user_id` (`push_subscription_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
