<?php

/**
 * Configurable email templates.
 *
 * Every outbound email in the app has a key here. renderEmailTemplate() looks up
 * an admin-edited row in the email_templates table and falls back to the default
 * below when there isn't one (including on a checkout that hasn't run the
 * email_templates migration yet) - so nothing changes for an install until
 * someone edits a template under Admin > Email Templates.
 *
 * Placeholders use {token} - renderEmailTemplate() does a plain string
 * replacement, so a template can drop a token entirely or use it more than once.
 */

function emailTemplateDefaults() {
    return [

        // --- Tickets ---

        'ticket_created' => [
            'name' => 'Ticket Created (agent/portal)',
            'subject' => 'Ticket Created - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello {contact_name},<br><br>A ticket regarding \"{ticket_subject}\" has been created for you.<br><br>--------------------------------<br>{ticket_details}--------------------------------<br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: {ticket_status}<br>Portal: <a href='{ticket_url}'>View ticket</a>{sla_notice}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, ticket_details, ticket_prefix, ticket_number, ticket_status, ticket_url, sla_notice, company_name, company_phone, from_email',
        ],
        'ticket_created_scheduled' => [
            'name' => 'Ticket Created (recurring ticket run)',
            'subject' => 'Ticket Created - [{ticket_prefix}{ticket_number}] - {ticket_subject} (scheduled)',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello {contact_name},<br><br>A ticket regarding \"{ticket_subject}\" has been automatically created for you.<br><br>--------------------------------<br>{ticket_details}--------------------------------<br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: Open<br>Portal: {ticket_url}{sla_notice}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, ticket_details, ticket_prefix, ticket_number, ticket_url, sla_notice, company_name, company_phone, from_email',
        ],
        'ticket_created_via_email' => [
            'name' => 'Ticket Created (from inbound email)',
            'subject' => 'Ticket created - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello {contact_name},<br><br>Thank you for your email. A ticket regarding \"{ticket_subject}\" has been automatically created for you.<br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: New<br>Portal: <a href='{ticket_url}'>View ticket</a>{sla_notice}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, ticket_prefix, ticket_number, ticket_url, sla_notice, company_name, company_phone, from_email',
        ],
        'ticket_notification_watcher_added' => [
            'name' => 'Ticket - Watcher Added Notification',
            'subject' => 'Ticket Notification - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello,<br><br>You have been added as a collaborator on this ticket regarding \"{ticket_subject}\".<br><br>--------------------------------<br>{ticket_details}--------------------------------<br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: {ticket_status}<br>Guest link: {ticket_url}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'ticket_subject, ticket_details, ticket_prefix, ticket_number, ticket_status, ticket_url, company_name, company_phone, from_email',
        ],
        'ticket_assigned_single' => [
            'name' => 'Ticket Assigned To You (single)',
            'subject' => '{app_name} - Ticket {ticket_prefix}{ticket_number} assigned to you - {ticket_subject}',
            'body' => "Hi {agent_name}, <br><br>A ticket has been assigned to you!<br><br>Client: {client_name}<br>Ticket Number: {ticket_prefix}{ticket_number}<br> Subject: {ticket_subject}<br><br>{ticket_url} <br><br>Thanks, <br>{session_name}<br>{company_name}",
            'tokens' => 'app_name, agent_name, client_name, ticket_prefix, ticket_number, ticket_subject, ticket_url, session_name, company_name',
        ],
        'ticket_assigned_bulk' => [
            'name' => 'Tickets Assigned To You (bulk)',
            'subject' => '{app_name} - {ticket_count} tickets have been assigned to you',
            'body' => "Hi {agent_name}, <br><br>{session_name} assigned {ticket_count} tickets to you!<br><br>{tickets_list}<br>Thanks, <br>{session_name}<br>{company_name}",
            'tokens' => 'app_name, agent_name, ticket_count, session_name, tickets_list, company_name',
        ],
        'recurring_ticket_assigned_bulk' => [
            'name' => 'Recurring Tickets Assigned To You (bulk)',
            'subject' => '{app_name} - {ticket_count} recurring tickets have been assigned to you',
            'body' => "Hi {agent_name}, <br><br>{session_name} assigned {ticket_count} recurring tickets to you!<br><br>{tickets_list}<br>Thanks, <br>{session_name}<br>{company_name}",
            'tokens' => 'app_name, agent_name, ticket_count, session_name, tickets_list, company_name',
        ],
        'ticket_resolved_pending_closure' => [
            'name' => 'Ticket Resolved - Pending Closure',
            'subject' => 'Ticket resolved - [{ticket_prefix}{ticket_number}] - {ticket_subject} | (pending closure)',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello {contact_name},<br><br>Your ticket regarding {ticket_subject} has been marked as solved and is pending closure.<br><br>--------------------------------<br>{ticket_reply}<br>--------------------------------<br><br>If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href='{ticket_reopen_url}'>re-open</a> to let us know! <br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: {ticket_status}<br>Portal: <a href='{ticket_url}'>View ticket</a><br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, ticket_reply, ticket_prefix, ticket_number, ticket_status, ticket_reopen_url, ticket_url, company_name, company_phone, from_email',
        ],
        'ticket_resolved_pending_closure_task' => [
            'name' => 'Ticket Resolved - Pending Closure (task completion)',
            'subject' => 'Ticket resolved - [{ticket_prefix}{ticket_number}] - {ticket_subject} | (pending closure)',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello {contact_name},<br><br>Your ticket regarding \"{ticket_subject}\" has been marked as solved and is pending closure.<br><br>{details}<br><br> If your request/issue is resolved, you can simply ignore this email. If you need further assistance, please reply or <a href='{ticket_reopen_url}'>re-open</a> to let us know! <br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Portal: {ticket_portal_url}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, details, ticket_prefix, ticket_number, ticket_reopen_url, ticket_portal_url, company_name, company_phone, from_email',
        ],
        'ticket_update' => [
            'name' => 'Ticket Update (public reply)',
            'subject' => 'Ticket update - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello {contact_name},<br><br>Your ticket regarding {ticket_subject} has been updated.<br><br>--------------------------------<br>{ticket_reply}<br>--------------------------------<br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: {ticket_status_name}<br>Portal: <a href='{ticket_url}'>View ticket</a><br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, ticket_reply, ticket_prefix, ticket_number, ticket_status_name, ticket_url, company_name, company_phone, from_email',
        ],
        'ticket_closed' => [
            'name' => 'Ticket Closed',
            'subject' => 'Ticket closed - [{ticket_prefix}{ticket_number}] - {ticket_subject} | (do not reply)',
            'body' => "Hello {contact_name},<br><br>Your ticket regarding \"{ticket_subject}\" has been closed. <br><br> We hope the request/issue was resolved to your satisfaction, please provide your feedback <a href='{feedback_url}'>here</a>. <br>If you need further assistance, please raise a new ticket using the below details. Please do not reply to this email. <br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Portal: {ticket_portal_url}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, ticket_subject, feedback_url, ticket_prefix, ticket_number, ticket_portal_url, company_name, company_phone, from_email',
        ],
        'ticket_scheduled_agent' => [
            'name' => 'Ticket Scheduled - Agent Notification',
            'subject' => 'Ticket Scheduled - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "Hello, {user_name}<br><br>The ticket regarding {ticket_subject} has been scheduled for {schedule_datetime}.<br><br>--------------------------------<br><a href=\"{ticket_url}\">{ticket_link}</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Portal: {ticket_url}<br><br>~<br>{company_name}<br>Support Department<br>{from_email}",
            'tokens' => 'user_name, ticket_subject, schedule_datetime, ticket_url, ticket_link, ticket_prefix, ticket_number, company_name, from_email',
        ],
        'ticket_scheduled_contact' => [
            'name' => 'Ticket Scheduled - Client Notification',
            'subject' => 'Ticket Scheduled - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<div class='header'>Hello, {contact_name}</div>Your ticket regarding {ticket_subject} has been scheduled for {schedule_datetime}.<br><br><a href='{ticket_portal_url}' class='link-button'>Access your ticket here</a><br><br>Please do not reply to this email.<br><br><strong>Ticket:</strong> {ticket_prefix}{ticket_number}<br><strong>Subject:</strong> {ticket_subject}<br><br><br><div class='footer'>~<br>{company_name}<br>Support Department<br>{from_email}<br></div><div class='no-reply'>This is an automated message. Please do not reply directly to this email.</div>",
            'tokens' => 'contact_name, ticket_subject, schedule_datetime, ticket_portal_url, ticket_prefix, ticket_number, company_name, from_email',
        ],
        'ticket_scheduled_watcher' => [
            'name' => 'Ticket Scheduled - Watcher Notification',
            'subject' => 'Ticket Scheduled - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<div class='header'>Hello,</div>The ticket regarding {ticket_subject} has been scheduled for {schedule_datetime}.<br><br><a href='{ticket_portal_url}' class='link-button'>{ticket_link}</a><br><br>Please do not reply to this email.<br><br><strong>Ticket:</strong> {ticket_prefix}{ticket_number}<br><strong>Subject:</strong> {ticket_subject}<br><strong>Portal:</strong> <a href='{ticket_portal_url}'>Access the ticket here</a><br><br><div class='footer'>~<br>{company_name}<br>Support Department<br>{from_email}<br></div><div class='no-reply'>This is an automated message. Please do not reply directly to this email.</div>",
            'tokens' => 'ticket_subject, schedule_datetime, ticket_portal_url, ticket_link, ticket_prefix, ticket_number, company_name, from_email',
        ],
        'ticket_schedule_cancelled_agent' => [
            'name' => 'Ticket Schedule Cancelled - Agent Notification',
            'subject' => 'Ticket Schedule Cancelled - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "Hello, {user_name}<br><br>Scheduled work for the ticket regarding {ticket_subject} has been cancelled.<br><br>--------------------------------<br><a href=\"{ticket_url}\">{ticket_link}</a><br>--------------------------------<br><br>Please do not reply to this email. <br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Portal: {ticket_portal_url}<br><br>~<br>{company_name}<br>Support Department<br>{from_email}",
            'tokens' => 'user_name, ticket_subject, ticket_url, ticket_link, ticket_prefix, ticket_number, ticket_portal_url, company_name, from_email',
        ],
        'ticket_schedule_cancelled_contact' => [
            'name' => 'Ticket Schedule Cancelled - Client Notification',
            'subject' => 'Ticket Schedule Cancelled - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<div class='header'>Hello, {contact_name}</div>Scheduled work for your ticket regarding {ticket_subject} has been cancelled.<br><br><a href='{ticket_portal_url}' class='link-button'>Access your ticket here</a><br><br>Please do not reply to this email.<br><br><strong>Ticket:</strong> {ticket_prefix}{ticket_number}<br><strong>Subject:</strong> {ticket_subject}<br><br><br><div class='footer'>~<br>{company_name}<br>Support Department<br>{from_email}<br></div><div class='no-reply'>This is an automated message. Please do not reply directly to this email.</div>",
            'tokens' => 'contact_name, ticket_subject, ticket_portal_url, ticket_prefix, ticket_number, company_name, from_email',
        ],
        'ticket_schedule_cancelled_watcher' => [
            'name' => 'Ticket Schedule Cancelled - Watcher Notification',
            'subject' => 'Ticket Schedule Cancelled - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<div class='header'>Hello,</div>Scheduled work for the ticket regarding {ticket_subject} has been cancelled.<br><br><a href='{ticket_portal_url}' class='link-button'>{ticket_link}</a><br><br>Please do not reply to this email.<br><br><strong>Ticket:</strong> {ticket_prefix}{ticket_number}<br><strong>Subject:</strong> {ticket_subject}<br><strong>Portal:</strong> <a href='{ticket_portal_url}'>Access the ticket here</a><br><br><div class='footer'>~<br>{company_name}<br>Support Department<br>{from_email}<br></div><div class='no-reply'>This is an automated message. Please do not reply directly to this email.</div>",
            'tokens' => 'ticket_subject, ticket_portal_url, ticket_link, ticket_prefix, ticket_number, company_name, from_email',
        ],
        'ticket_task_approval' => [
            'name' => 'Ticket Task Approval Required',
            'subject' => 'Ticket task approval required - [{ticket_prefix}{ticket_number}] - {ticket_subject}',
            'body' => "<i style='color: #808080'>##- Please type your reply above this line -##</i><br><br>Hello,<br><br>A ticket regarding {ticket_subject} has a task requiring your approval:- <br>Task name: {task_name}<br>Scope/Type: {scope} - {type} <br><br>To approve this task, please click <a href='{approval_url}'>here</a>.<br>If you require further information, please reply to this e-mail.<br><br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br>Status: {ticket_status}<br>Portal: <a href='{ticket_url}'>View ticket</a><br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'ticket_subject, task_name, scope, type, approval_url, ticket_prefix, ticket_number, ticket_status, ticket_url, company_name, company_phone, from_email',
        ],
        'ticket_reopen_blocked' => [
            'name' => 'Ticket Reply Rejected - Ticket Closed',
            'subject' => 'Action required: This ticket is already closed',
            'body' => "Hi there, <br><br>You've tried to reply to a ticket that is closed - we won't see your response. <br><br>Please raise a new ticket by sending a new e-mail to our support address below. <br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'company_name, company_phone, from_email',
        ],
        'ticket_reply_tech_notification' => [
            'name' => 'Ticket Reply - Assigned Tech Notification',
            'subject' => '{app_name} Ticket updated - [{ticket_prefix}{ticket_number}] {ticket_subject}',
            'body' => "Hello {tech_name},<br><br>A new reply has been added to the below ticket, check {app_name} for full details.<br><br>Client: {client_name}<br>Ticket: {ticket_prefix}{ticket_number}<br>Subject: {ticket_subject}<br><br>{ticket_url}",
            'tokens' => 'app_name, tech_name, client_name, ticket_prefix, ticket_number, ticket_subject, ticket_url',
        ],
        'new_ticket_notification_internal' => [
            'name' => 'New Ticket - Internal Notification',
            'subject' => '{app_name} - New Ticket - {client_name}: {ticket_subject}',
            'body' => "Hello, <br><br>This is a notification that a new ticket has been raised in {app_name}. <br>Client: {client_name}<br>Priority: {priority}<br>Link: {ticket_url} <br><br>--------------------------------<br><br><b>{ticket_subject}</b><br>{ticket_details}",
            'tokens' => 'app_name, client_name, ticket_subject, priority, ticket_url, ticket_details',
        ],
        'new_recurring_ticket_notification_internal' => [
            'name' => 'New Recurring Ticket - Internal Notification',
            'subject' => '{app_name} - New Recurring Ticket - {client_name}: {ticket_subject}',
            'body' => "Hello, <br><br>This is a notification that a recurring (scheduled) ticket has been raised in {app_name}. <br>Ticket: {ticket_prefix}{ticket_number}<br>Client: {client_name}<br>Priority: {priority}<br>Link: {ticket_url} <br><br>--------------------------------<br><br><b>{ticket_subject}</b><br>{ticket_details}",
            'tokens' => 'app_name, ticket_prefix, ticket_number, client_name, ticket_subject, priority, ticket_url, ticket_details',
        ],
        'ticket_sla_alert' => [
            'name' => 'Ticket SLA Alert',
            'subject' => '{sla_event}: {ticket_ref} - {ticket_subject}',
            'body' => "Hello,<br><br>{sla_message}<br><br>Ticket: {ticket_ref}<br>Subject: {ticket_subject}<br><br><a href='{ticket_url}'>View ticket</a>",
            'tokens' => 'sla_event, ticket_ref, ticket_subject, sla_message, ticket_url',
        ],

        // --- Billing ---

        'invoice_created' => [
            'name' => 'Invoice Created',
            'subject' => 'Invoice {invoice_prefix}{invoice_number}',
            'body' => "Hello {contact_name},<br><br>An invoice regarding \"{invoice_scope}\" has been generated. Please view the details below.<br><br>Invoice: {invoice_prefix}{invoice_number}<br>Issue Date: {invoice_date}<br>Total: {invoice_total}<br>Due Date: {invoice_due}<br><br><br>To view your invoice, please click <a href='{invoice_url}'>here</a>.<br><br><br>--<br>{company_name} - Billing<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, invoice_scope, invoice_prefix, invoice_number, invoice_date, invoice_total, invoice_due, invoice_url, company_name, company_phone, from_email',
        ],
        'invoice_paid_link' => [
            'name' => 'Invoice - Paid Copy',
            'subject' => 'Invoice {invoice_prefix}{invoice_number} Receipt',
            'body' => "Hello {contact_name},<br><br>Please click on the link below to see your invoice regarding \"{invoice_scope}\" marked <b>paid</b>.<br><br><a href='{invoice_url}'>Invoice Link</a><br><br><br>--<br>{company_name} - Billing<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, invoice_scope, invoice_url, company_name, company_phone, from_email',
        ],
        'invoice_overdue' => [
            'name' => 'Invoice Overdue Reminder',
            'subject' => 'Overdue Invoice {invoice_prefix}{invoice_number}',
            'body' => "Hello {contact_name},<br><br>Our records indicate that we have not yet received payment in full for the invoice {invoice_prefix}{invoice_number}. We kindly request that you submit your payment as soon as possible. If you have any questions or concerns, please do not hesitate to contact us at {company_email} or {company_phone}.<br>Kindly review the invoice details mentioned below.<br><br>Invoice: {invoice_prefix}{invoice_number}<br>Issue Date: {invoice_date}<br>Invoice Total: {invoice_total}<br>{paid_line}Balance Due: {invoice_balance}<br>Due Date: {invoice_due}<br>Over Due By: {days_overdue} Days<br><br><br>To view your invoice, please click <a href='{invoice_url}'>here</a>.<br><br><br>--<br>{company_name} - Billing<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, invoice_prefix, invoice_number, company_email, company_phone, invoice_date, invoice_total, paid_line, invoice_balance, invoice_due, days_overdue, invoice_url, company_name, from_email',
        ],
        'invoice_statement' => [
            'name' => 'Account Statement',
            'subject' => 'Account Statement - {client_name}',
            'body' => "Hello {contact_name},<br><br>Please find your account statement for {statement_period} below.<br><br>{statement_table}<br><br>Click any invoice number above to view or pay it online.<br><br><br>--<br>{company_name} - Billing<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, client_name, statement_period, statement_table, company_name, company_phone, from_email',
        ],
        'quote_created' => [
            'name' => 'Quote Sent',
            'subject' => 'Quote [{quote_scope}]',
            'body' => "Hello {contact_name},<br><br>Thank you for your inquiry, we are pleased to provide you with the following estimate.<br><br><br>{quote_scope}<br>Total Cost: {quote_total}<br><br><br>View and accept your estimate online <a href='{quote_url}'>here</a><br><br><br>--<br>{company_name} - Sales<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, quote_scope, quote_total, quote_url, company_name, company_phone, from_email',
        ],
        'quote_accepted_internal' => [
            'name' => 'Quote Accepted - Internal Notification',
            'subject' => 'Quote Accepted - {client_name} - Quote {quote_prefix}{quote_number}',
            'body' => "Hello, <br><br>This is a notification that a quote has been accepted in {app_name}. <br><br>Client: {client_name}<br>Quote: <a href='{quote_url}'>{quote_prefix}{quote_number}</a><br><br>~<br>{company_name} - Billing<br>{from_email}",
            'tokens' => 'app_name, client_name, quote_prefix, quote_number, quote_url, company_name, from_email',
        ],
        'quote_declined_internal' => [
            'name' => 'Quote Declined - Internal Notification',
            'subject' => 'Quote Declined - {client_name} - Quote {quote_prefix}{quote_number}',
            'body' => "Hello, <br><br>This is a notification that a quote has been declined in {app_name}. <br><br>Client: {client_name}<br>Quote: <a href='{quote_url}'>{quote_prefix}{quote_number}</a><br><br>~<br>{company_name} - Billing<br>{from_email}",
            'tokens' => 'app_name, client_name, quote_prefix, quote_number, quote_url, company_name, from_email',
        ],
        'payment_received_full' => [
            'name' => 'Payment Received - Paid In Full (manual)',
            'subject' => 'Payment Received - Invoice {invoice_prefix}{invoice_number}',
            'body' => "Hello {contact_name},<br><br>We have received your payment in full for the amount of {amount} for invoice <a href='{invoice_url}'>{invoice_prefix}{invoice_number}</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: {amount}<br>Payment Method: {payment_method}<br>Payment Reference: {payment_reference}<br><br>Thank you for your business!<br><br><br>--<br>{company_name} - Billing Department<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, amount, invoice_url, invoice_prefix, invoice_number, payment_method, payment_reference, company_name, company_phone, from_email',
        ],
        'payment_received_partial' => [
            'name' => 'Payment Received - Partial (manual)',
            'subject' => 'Partial Payment Received - Invoice {invoice_prefix}{invoice_number}',
            'body' => "Hello {contact_name},<br><br>We have received partial payment in the amount of {amount} and it has been applied to invoice <a href='{invoice_url}'>{invoice_prefix}{invoice_number}</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: {amount}<br>Payment Method: {payment_method}<br>Payment Reference: {payment_reference}<br>Invoice Balance: {invoice_balance}<br><br>Thank you for your business!<br><br><br>~<br>{company_name} - Billing<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, amount, invoice_url, invoice_prefix, invoice_number, payment_method, payment_reference, invoice_balance, company_name, company_phone, from_email',
        ],
        'payment_received_online' => [
            'name' => 'Payment Received - Online/Autopay',
            'subject' => 'Payment Received - Invoice {invoice_prefix}{invoice_number}',
            'body' => "Hello {contact_name},<br><br>We have received online payment for the amount of {amount} for invoice <a href='{invoice_url}'>{invoice_prefix}{invoice_number}</a>. Please keep this email as a receipt for your records.<br><br>Amount Paid: {amount}<br><br>Thank you for your business!<br><br><br>--<br>{company_name} - Billing Department<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, amount, invoice_url, invoice_prefix, invoice_number, company_name, company_phone, from_email',
        ],
        'payment_received_internal' => [
            'name' => 'Payment Received - Internal Notification',
            'subject' => 'Payment Received - {client_name} - Invoice {invoice_prefix}{invoice_number}',
            'body' => "Hello, <br><br>This is a notification that an invoice has been paid in {app_name}. Below is a copy of the receipt sent to the client:-<br><br>--------<br><br>{client_receipt_body}",
            'tokens' => 'app_name, client_name, invoice_prefix, invoice_number, client_receipt_body',
        ],
        'payment_received_multiple' => [
            'name' => 'Payment Received - Multiple Invoices',
            'subject' => 'Payment Received - Multiple Invoices',
            'body' => "Hello {contact_name},<br><br>Thank you for your payment of {amount} We've applied your payment to the following invoices, updating their balances accordingly:<br><br>{invoice_list}<br><br><br>We appreciate your continued business!<br><br>Sincerely,<br>{company_name} - Billing<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, amount, invoice_list, company_name, company_phone, from_email',
        ],
        'payment_method_saved' => [
            'name' => 'Payment Method Saved',
            'subject' => 'Payment method saved',
            'body' => "Hello {contact_name}<br><br>We're writing to confirm that your payment details have been securely stored with Stripe our trusted payment processor.<br><br>You authorized us to automatically bill your card ({payment_description}) for future invoices.<br><br>You may update or remove your payment method at any time via the client portal.<br><br>Thank you for your business!<br><br>--<br>{company_name} - Billing Department<br>{from_email}<br>{company_phone}",
            'tokens' => 'contact_name, payment_description, company_name, company_phone, from_email',
        ],

        // --- Auth / account ---

        'password_reset_client' => [
            'name' => 'Client Portal - Password Reset Link',
            'subject' => 'Password reset for {company_name} Client Portal',
            'body' => "Hello {name},<br><br>Someone (probably you) has requested a new password for your account on {company_name}'s Client Portal. <br><br><b>Please <a href='{reset_url}'>click here</a> to reset your password.</b> <br><br>Alternatively, copy and paste this URL into your browser:<br> {reset_url}<br><br><i>If you didn't request this change, you can safely ignore this email.</i><br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'name, company_name, reset_url, company_phone, from_email',
        ],
        'password_reset_confirm_client' => [
            'name' => 'Client Portal - Password Reset Confirmation',
            'subject' => 'Password reset confirmation for {company_name} Client Portal',
            'body' => "Hello {name},<br><br>Your password for your account on {company_name}'s Client Portal was successfully reset. You should be all set! <br><br><b>If you didn't reset your password, please get in touch ASAP.</b><br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'name, company_name, company_phone, from_email',
        ],
        'new_user_account' => [
            'name' => 'New Agent Account Created',
            'subject' => 'Your new {company_name} {app_name} account',
            'body' => "Hello {name},<br><br>An {app_name} account has been setup for you. Please change your password upon login. <br><br>Username: {email} <br>Password: {password}<br>Login URL: {login_url}<br><br>--<br>{company_name} - Support<br>{from_email}",
            'tokens' => 'name, app_name, company_name, email, password, login_url, from_email',
        ],
        'account_update_confirmation' => [
            'name' => 'Agent Account Updated',
            'subject' => '{app_name} account update confirmation for {name}',
            'body' => "Hi {name}, <br><br>Your {app_name} account has been updated, details below: <br><br> <b>{details}</b> <br><br> If you did not perform this change, contact your {app_name} administrator immediately. <br><br>Thanks, <br>{app_name}<br>{company_name}",
            'tokens' => 'app_name, name, details, company_name',
        ],
        'new_login_notification' => [
            'name' => 'New/Unusual Login Notification',
            'subject' => '{app_name} new login for {user_name}',
            'body' => "Hi {user_name}, <br><br>A recent successful login to your {app_name} account was considered a little unusual. If this was you, you can safely ignore this email!<br><br>IP Address: {ip_address}<br> User Agent: {user_agent} <br><br>If you did not perform this login, your credentials may be compromised. <br><br>Thanks, <br>{app_name}",
            'tokens' => 'app_name, user_name, ip_address, user_agent',
        ],
        'failed_2fa_notification' => [
            'name' => 'Failed 2FA Login Notification',
            'subject' => 'Important: {app_name} failed 2FA login attempt for {user_name}',
            'body' => "Hi {user_name}, <br><br>A recent login to your {app_name} account was unsuccessful due to an incorrect 2FA code. If you did not attempt this login, your credentials may be compromised. <br><br>Thanks, <br>{app_name}",
            'tokens' => 'app_name, user_name',
        ],

        // --- Misc ---

        'secure_link_share' => [
            'name' => 'Secure Link Share',
            'subject' => '{subject_prefix}{company_name} secure link enclosed',
            'body' => "Hello,<br><br>{session_name} from {company_name} sent you a time sensitive secure link regarding \"{item_name}\".<br><br>The link will expire in <strong>{item_expires_friendly}</strong>{item_view_limit_wording}.<br><br><strong><a href='{share_url}'>Click here to access your secure content</a></strong><br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}<br><br><em>This email and any attachments are confidential and intended for the specified recipient(s) only. If you are not the intended recipient, please notify the sender and delete this email. Unauthorized use, disclosure, or distribution is prohibited.</em>",
            'tokens' => 'subject_prefix, company_name, session_name, item_name, item_expires_friendly, item_view_limit_wording, share_url, from_email, company_phone',
        ],
        'calendar_event_invite' => [
            'name' => 'Calendar Event Invite',
            'subject' => 'New Calendar Event',
            'body' => "Hello {contact_name},<br><br>A calendar event has been scheduled:<br><br>Event Title: {event_title}<br>Event Date: {event_start}<br><br><br>--<br>{company_name}<br>{company_phone}",
            'tokens' => 'contact_name, event_title, event_start, company_name, company_phone',
        ],
        'calendar_event_rescheduled' => [
            'name' => 'Calendar Event Rescheduled',
            'subject' => 'Calendar Event Rescheduled',
            'body' => "Hello {contact_name},<br><br>A calendar event has been rescheduled:<br><br>Event Title: {event_title}<br>Event Date: {event_start}<br><br><br>--<br>{company_name}<br>{company_phone}",
            'tokens' => 'contact_name, event_title, event_start, company_name, company_phone',
        ],
        'contact_portal_account_created' => [
            'name' => 'Client Portal Account Created',
            'subject' => 'Your new {company_name} portal account',
            'body' => "Hello {name},<br><br>{company_name} has created a support portal account for you. <br><br>Username: {email}<br>Password: {password_info}<br><br>Login URL: {login_url}<br><br>--<br>{company_name} - Support<br>{from_email}<br>{company_phone}",
            'tokens' => 'name, company_name, email, password_info, login_url, company_phone, from_email',
        ],
        'update_available_notification' => [
            'name' => 'ITFlow Update Available',
            'subject' => 'ITFlow update available',
            'body' => "A new ITFlow update is available for the {company_name} install.<br><br>Currently running: <code>{current_version}</code><br>Latest available: <code>{latest_version}</code><br><br>Review the <a href=\"https://github.com/itflow-org/itflow/blob/master/CHANGELOG.md\">changelog</a> and back up before updating, then apply it from Admin &gt; Update.",
            'tokens' => 'company_name, current_version, latest_version',
        ],

    ];
}

/**
 * Fetch a template (admin override merged over the built-in default) by key.
 * Cached per-request since the same key is often rendered for several
 * recipients (contact + watchers) in one request.
 */
function getEmailTemplate($key) {
    global $mysqli;
    static $cache = [];

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $defaults = emailTemplateDefaults();
    $template = $defaults[$key] ?? ['name' => $key, 'subject' => '', 'body' => '', 'tokens' => ''];

    $key_esc = mysqli_real_escape_string($mysqli, $key);
    $result = mysqli_query($mysqli, "SELECT email_template_subject, email_template_body FROM email_templates WHERE email_template_key = '$key_esc' LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $template['subject'] = $row['email_template_subject'];
        $template['body'] = $row['email_template_body'];
    }

    $cache[$key] = $template;
    return $template;
}

/**
 * Render a template's subject/body with {token} => value substitution.
 * Returns ['subject' => ..., 'body' => ...].
 */
function renderEmailTemplate($key, $vars = []) {
    $template = getEmailTemplate($key);

    $replacements = [];
    foreach ($vars as $token => $value) {
        $replacements['{' . $token . '}'] = (string) $value;
    }

    return [
        'subject' => strtr($template['subject'], $replacements),
        'body' => strtr($template['body'], $replacements),
    ];
}
