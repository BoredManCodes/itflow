<?php

/*
 * ITFlow - Database update to version 2.7.19 (from 2.7.18)
 * Included by admin/database_updates.php - do not access directly
 *
 * ticket_replies.ticket_reply_emailed_at is when a reply was emailed to the
 * client and watchers, so a public reply and a public reply that was emailed
 * can be told apart. Replies from before this update stay empty.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "ALTER TABLE `ticket_replies`
    ADD COLUMN IF NOT EXISTS `ticket_reply_emailed_at` datetime DEFAULT NULL AFTER `ticket_reply_updated_at`");
