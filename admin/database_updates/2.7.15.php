<?php

/*
 * ITFlow - Database update to version 2.7.15 (from 2.7.14)
 * Included by admin/database_updates.php - do not access directly
 *
 * Adds Entra ID <-> ITFlow sync: entra_tenants maps an ITFlow client to an
 * Entra tenant and holds the app-only Graph credentials cron/entra_sync.php
 * authenticates with; entra_sync_contacts and entra_sync_assets link synced
 * Entra users/devices to their contact/asset rows and keep a snapshot of the
 * last-synced contact fields, which is how the two-way contact sync tells
 * which side changed since the last run.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "CREATE TABLE `entra_tenants` (
    `entra_tenant_id` int(11) NOT NULL AUTO_INCREMENT,
    `entra_tenant_client_id` int(11) NOT NULL COMMENT 'ITFlow client this Entra tenant maps to',
    `entra_tenant_name` varchar(200) NOT NULL COMMENT 'Label only, e.g. myteamss.com.au',
    `entra_tenant_directory_id` varchar(64) NOT NULL COMMENT 'Azure AD/Entra tenant GUID',
    `entra_tenant_app_id` varchar(64) NOT NULL COMMENT 'App registration (client) ID used for client-credentials auth',
    `entra_tenant_app_secret` varchar(255) NOT NULL COMMENT 'App registration client secret - same plaintext trust model as config_mail_oauth_client_secret',
    `entra_tenant_sync_contacts` tinyint(1) NOT NULL DEFAULT 1,
    `entra_tenant_sync_assets` tinyint(1) NOT NULL DEFAULT 1,
    `entra_tenant_sync_licenses` tinyint(1) NOT NULL DEFAULT 1,
    `entra_tenant_writeback_contacts` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Push ITFlow contact field edits back to the matching Entra user',
    `entra_tenant_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `entra_tenant_last_sync_at` datetime DEFAULT NULL,
    `entra_tenant_last_error` varchar(500) DEFAULT NULL,
    `entra_tenant_created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`entra_tenant_id`),
    UNIQUE KEY `entra_tenant_client_id` (`entra_tenant_client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

mysqli_query($mysqli, "CREATE TABLE `entra_sync_contacts` (
    `entra_sync_contact_id` int(11) NOT NULL AUTO_INCREMENT,
    `entra_sync_contact_tenant_id` int(11) NOT NULL COMMENT 'FK to entra_tenants.entra_tenant_id',
    `entra_sync_contact_object_id` varchar(64) NOT NULL COMMENT 'Entra user object ID',
    `entra_sync_contact_contact_id` int(11) NOT NULL COMMENT 'FK to contacts.contact_id',
    `entra_sync_contact_last_display_name` varchar(200) DEFAULT NULL COMMENT 'Snapshot of the last-synced value, used to tell which side changed',
    `entra_sync_contact_last_title` varchar(200) DEFAULT NULL,
    `entra_sync_contact_last_department` varchar(200) DEFAULT NULL,
    `entra_sync_contact_last_mobile` varchar(200) DEFAULT NULL,
    `entra_sync_contact_last_business_phone` varchar(200) DEFAULT NULL,
    `entra_sync_contact_last_synced_at` datetime DEFAULT NULL,
    PRIMARY KEY (`entra_sync_contact_id`),
    UNIQUE KEY `entra_sync_contact_object_id` (`entra_sync_contact_tenant_id`,`entra_sync_contact_object_id`),
    UNIQUE KEY `entra_sync_contact_contact_id` (`entra_sync_contact_contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

mysqli_query($mysqli, "CREATE TABLE `entra_sync_assets` (
    `entra_sync_asset_id` int(11) NOT NULL AUTO_INCREMENT,
    `entra_sync_asset_tenant_id` int(11) NOT NULL COMMENT 'FK to entra_tenants.entra_tenant_id',
    `entra_sync_asset_device_id` varchar(64) NOT NULL COMMENT 'Entra device object ID',
    `entra_sync_asset_asset_id` int(11) NOT NULL COMMENT 'FK to assets.asset_id',
    `entra_sync_asset_last_synced_at` datetime DEFAULT NULL,
    PRIMARY KEY (`entra_sync_asset_id`),
    UNIQUE KEY `entra_sync_asset_device_id` (`entra_sync_asset_tenant_id`,`entra_sync_asset_device_id`),
    UNIQUE KEY `entra_sync_asset_asset_id` (`entra_sync_asset_asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Per-tenant rows (client_id, directory ID, app ID, secret) are install-specific data,
// applied separately after this migration runs - see the deploy notes for this change.
