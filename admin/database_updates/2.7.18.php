<?php

/*
 * ITFlow - Database update to version 2.7.18 (from 2.7.17)
 * Included by admin/database_updates.php - do not access directly
 *
 * Closing a ticket directly (agent or client portal) set ticket_closed_at
 * but never ticket_resolved_at, so those tickets stayed stuck in the Open
 * tab/count forever despite showing a Closed status badge. Backfill the
 * ones already affected now that the close actions are fixed.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

mysqli_query($mysqli, "UPDATE tickets SET ticket_resolved_at = ticket_closed_at
    WHERE ticket_status = 5 AND ticket_resolved_at IS NULL AND ticket_closed_at IS NOT NULL");
