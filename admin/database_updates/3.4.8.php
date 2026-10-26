<?php

/*
 * ITFlow - Database update to version 3.4.8 (from 3.4.7)
 * Included by admin/database_updates.php - do not access directly
 *
 * ticket_replies.ticket_reply_emailed_at is when a reply was emailed to the
 * client and watchers, so a public reply and a public reply that was emailed
 * can be told apart. Replies from before this update stay empty - what was
 * emailed then wasn't recorded.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_replies' AND COLUMN_NAME = 'ticket_reply_emailed_at' LIMIT 1");

    if (!$itflow_column || mysqli_num_rows($itflow_column) == 0) {
        mysqli_query($mysqli, "ALTER TABLE `ticket_replies` ADD `ticket_reply_emailed_at` datetime DEFAULT NULL AFTER `ticket_reply_updated_at`");
    }

    unset($itflow_column);
