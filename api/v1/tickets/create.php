<?php

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Ticket-related settings
require_once "../../../includes/load_global_settings.php";

$sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
$row = mysqli_fetch_assoc($sql);
$company_name = $row['company_name'];
$company_phone = formatPhoneNumber($row['company_phone']);

// Parse Info
$ticket_row = false; // Creation, not an update
require_once 'ticket_model.php';

// Default
$insert_id = false;

if (!empty($subject)) {

    if (!is_int($client_id)) {
        $client_id = 0;
    }

    // If no contact is selected automatically choose the primary contact for the client (if client set)
    if ($contact == 0 && $client_id != 0) {
        $sql = mysqli_query($mysqli,"SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_assoc($sql);
        $contact = intval($row['contact_id']);
    }

    // Atomically increment and get the new ticket number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");

    $ticket_number = mysqli_insert_id($mysqli);

    // Insert ticket
    $url_key = randomString(32);
    $insert_sql = mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'API', ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 1, ticket_billable = $billable, ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_created_by = 0, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_asset_id = $asset, ticket_url_key = '$url_key', ticket_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);
        applyTicketSla($insert_id);
        applyTicketAutoAssign($insert_id);
        applyTicketRules($insert_id);

        $client_uri = $client_id ? "&client_id=$client_id" : '';

        // Notify agent DL of the new ticket, if populated with a valid email
        $config_ticket_new_ticket_notification_email = filter_var($config_ticket_new_ticket_notification_email, FILTER_VALIDATE_EMAIL);
        if ($config_ticket_new_ticket_notification_email) {
            if ($client_id) {
                $client_name_sql = mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id LIMIT 1");
                $client_name_row = mysqli_fetch_assoc($client_name_sql);
                $client_name = escapeSql($client_name_row['client_name'] ?? '');
            } else {
                $client_name = "API";
            }

            $rendered = renderEmailTemplate('new_ticket_notification_internal', [
                'app_name' => $config_app_name,
                'client_name' => $client_name,
                'ticket_subject' => $subject,
                'priority' => $priority,
                'ticket_url' => "https://$config_base_url/agent/ticket.php?ticket_id=$insert_id$client_uri",
                'ticket_details' => $details,
            ]);

            $data = [
                [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $config_ticket_new_ticket_notification_email,
                    'recipient_name' => $config_ticket_from_name,
                    'subject' => $rendered['subject'],
                    'body' => $rendered['body'],
                ]
            ];
            addToMailQueue($data);
        }

        // Notify techs of the new (unassigned) ticket
        appNotify("Ticket", "New ticket via API ($api_key_name): $subject", "/agent/ticket.php?ticket_id=$insert_id$client_uri", $client_id, $insert_id);

        // Custom action/notif handler
        triggerCustomAction('ticket_create', $insert_id);

        // Logging
        logAudit("Ticket", "Create", "Created ticket $config_ticket_prefix$ticket_number $subject via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created ticket $config_ticket_prefix$ticket_number $subject via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
