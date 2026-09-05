<?php

/*
 * ITFlow - Database update to version 2.7.16 (from 2.7.15)
 * Included by admin/database_updates.php - do not access directly
 *
 * Tracks whether a client portal contact has completed the first-visit
 * portal walkthrough, so client/index.php shows it at most once per contact.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `contacts`
    ADD COLUMN `contact_portal_tutorial_seen_at` DATETIME DEFAULT NULL COMMENT 'When this contact dismissed/completed the client portal first-visit tour'");
