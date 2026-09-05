<?php

/*
 * CRON - Entra ID Sync
 *
 * For every row in entra_tenants, authenticates to that Entra tenant with app-only
 * (client-credentials) Graph access and syncs:
 *   - Contacts:  Entra users <-> ITFlow contacts, matched on email/UPN. Core profile
 *                fields (name, title, department, mobile, business phone) sync from
 *                Entra by default; when entra_tenant_writeback_contacts is on, an edit
 *                made in ITFlow since the last run is pushed back to the Entra user
 *                instead of being overwritten. Login and license data always flows
 *                Entra -> ITFlow only and is written into a marked block inside
 *                contact_notes rather than a dedicated column.
 *   - Assets:    Entra/Intune devices -> ITFlow assets, one-directional. Matched on
 *                the Entra device object ID once linked, or by name on first sight.
 *
 * Conflict rule for the two-way contact fields: entra_sync_contacts keeps a snapshot of
 * each field's value as of the last successful sync. If the ITFlow side changed since
 * that snapshot and the Entra side did not, the ITFlow value is pushed to Entra. Anything
 * else - Entra changed, both changed, or neither did - Entra wins, since it is the
 * authoritative directory. This needs no field-level "last modified" timestamp from
 * either side, only the one snapshot column pair per field.
 *
 * Each tenant is wrapped in its own try/catch so one tenant's auth failure or a bad
 * Graph response does not stop the others from syncing.
 */

$script_start_time = microtime(true);

chdir(dirname(__FILE__));

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$cron_lock_script = __FILE__;
require_once "includes/cron_lock.php";

require_once "../config.php";
require_once "../includes/inc_set_timezone.php";
require_once "../functions.php";
require_once "../includes/load_global_settings.php";

if ($config_enable_cron == 0) {
    logApp("Cron-Entra-Sync", "error", "Cron Entra Sync unable to run - cron not enabled in admin settings.");
    cronJobStop("Cron: is not enabled -- Quitting..");
}

/** ------------------------------------------------------------------
 * Graph HTTP helpers
 * ------------------------------------------------------------------ */

// Client-credentials (app-only) token for one tenant. Returns the access token string,
// or null - callers log and skip the tenant rather than treat this as fatal for the run.
function entraGetAppToken(string $directory_id, string $app_id, string $app_secret): ?string {
    $response = httpFormPost("https://login.microsoftonline.com/" . rawurlencode($directory_id) . "/oauth2/v2.0/token", [
        'client_id' => $app_id,
        'client_secret' => $app_secret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials',
    ]);

    if (!$response['ok']) {
        return null;
    }

    $payload = json_decode((string) $response['body'], true);

    return $payload['access_token'] ?? null;
}

// One JSON request against Graph, with a couple of retries on throttling/transient errors.
function entraGraphRequest(string $method, string $url, ?string $token, ?array $body = null): array {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ch = curl_init($url);
        $headers = ["Authorization: Bearer $token", "Content-Type: application/json"];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw !== false && ($code === 429 || $code >= 500) && $attempt < 3) {
            sleep($attempt * 2);
            continue;
        }

        return [
            'ok' => ($raw !== false && $code >= 200 && $code < 300),
            'code' => $code,
            'body' => $raw,
            'err' => $err,
        ];
    }

    return ['ok' => false, 'code' => 0, 'body' => null, 'err' => $err ?? 'request failed'];
}

// GET a collection, following @odata.nextLink until exhausted. Returns the combined
// 'value' array, or null if any page failed - callers treat that as "could not sync".
function entraGraphGetAll(string $url, string $token): ?array {
    $items = [];

    while ($url !== null) {
        $response = entraGraphRequest('GET', $url, $token);
        if (!$response['ok']) {
            return null;
        }

        $payload = json_decode((string) $response['body'], true);
        if (!is_array($payload) || !isset($payload['value'])) {
            return null;
        }

        foreach ($payload['value'] as $item) {
            $items[] = $item;
        }

        $url = $payload['@odata.nextLink'] ?? null;
    }

    return $items;
}

function entraGraphPatch(string $url, string $token, array $body): bool {
    return entraGraphRequest('PATCH', $url, $token, $body)['ok'];
}

/** ------------------------------------------------------------------
 * Small helpers
 * ------------------------------------------------------------------ */

// mysqli_real_escape_string directly, not escapeSql() - escapeSql() strip_tags()'s its
// input, which would mangle a job title or note body that happens to contain < or >.
// Every value that ends up on a page is escapeHtml()'d on output regardless of what is
// stored, so there is nothing to gain and real Entra data to lose by stripping here.
function sqlEsc($value): string {
    global $mysqli;
    return mysqli_real_escape_string($mysqli, (string) ($value ?? ''));
}

// First non-empty string, or ''.
function firstNonEmpty(...$values): string {
    foreach ($values as $value) {
        $value = trim((string) ($value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

// Best-effort E.164-ish split for a number coming FROM Entra, so it lands in ITFlow's
// separate country-code/number fields instead of only ever populating one of them.
// Handles the common "+<country><number>" shape; anything else is left ungrouped in
// the number field with no country code guessed, rather than guessed wrong.
function splitPhoneFromEntra(string $number): array {
    $number = trim($number);
    if ($number === '') {
        return ['country_code' => '', 'number' => ''];
    }
    if (preg_match('/^\+(\d{1,3})[\s.-]?(.*)$/', $number, $m)) {
        return ['country_code' => $m[1], 'number' => preg_replace('/\D/', '', $m[2])];
    }
    return ['country_code' => '', 'number' => preg_replace('/\D/', '', $number)];
}

// Inverse, for pushing an ITFlow contact's phone fields back to Entra as one string.
function combinePhoneForEntra(string $country_code, string $number): string {
    $number = preg_replace('/\D/', '', $number);
    if ($number === '') {
        return '';
    }
    return $country_code !== '' ? "+$country_code$number" : $number;
}

// Replaces just the auto-managed block inside a notes field, leaving anything a
// human wrote above or below it untouched. Used for both contact_notes (licenses,
// sign-in state) and asset_notes (Entra device detail) since neither has dedicated
// columns for this and both are free-text today.
function updateManagedNotesBlock(string $existing_notes, array $lines): string {
    $start = '----- ITFlow Entra Sync (auto-managed, do not edit below) -----';
    $end = '----- End Entra Sync -----';
    $block = $start . "\n" . implode("\n", $lines) . "\n" . $end;

    $start_pos = strpos($existing_notes, $start);
    $end_pos = strpos($existing_notes, $end);

    if ($start_pos !== false && $end_pos !== false && $end_pos > $start_pos) {
        $before = trim(substr($existing_notes, 0, $start_pos));
        return ($before !== '' ? "$before\n\n" : '')
            . $block
            . substr($existing_notes, $end_pos + strlen($end));
    }

    $existing_notes = trim($existing_notes);
    return $existing_notes !== '' ? "$existing_notes\n\n$block" : $block;
}

// SKU part numbers are cryptic (O365_BUSINESS_PREMIUM, POWER_BI_STANDARD) - map the
// ones actually seen in these tenants to a readable label; fall back to the raw part
// number for anything not in the list rather than hiding it.
function friendlySkuName(string $sku_part_number): string {
    $known = [
        'O365_BUSINESS_PREMIUM' => 'Microsoft 365 Business Premium',
        'SPB' => 'Microsoft 365 Business Premium',
        'O365_BUSINESS_ESSENTIALS' => 'Microsoft 365 Business Basic',
        'O365_BUSINESS' => 'Microsoft 365 Apps for Business',
        'SPE_E3' => 'Microsoft 365 E3',
        'SPE_E5' => 'Microsoft 365 E5',
        'Microsoft_365_Copilot' => 'Microsoft 365 Copilot',
        'POWER_BI_STANDARD' => 'Power BI (Free)',
        'POWER_BI_PRO' => 'Power BI Pro',
        'FLOW_FREE' => 'Power Automate (Free)',
        'WINDOWS_STORE' => 'Windows Store for Business',
        'EXCHANGEDESKLESS' => 'Exchange Online Kiosk',
        'EXCHANGESTANDARD' => 'Exchange Online Plan 1',
    ];
    return $known[$sku_part_number] ?? $sku_part_number;
}

/** ------------------------------------------------------------------
 * Contact sync
 * ------------------------------------------------------------------ */

function entraSyncContacts(array $tenant, string $token, array $sku_names): array {
    global $mysqli;

    $tenant_id = intval($tenant['entra_tenant_id']);
    $client_id = intval($tenant['entra_tenant_client_id']);
    $writeback = !empty($tenant['entra_tenant_writeback_contacts']);
    $now = date('Y-m-d H:i:s');

    $select = 'id,displayName,mail,userPrincipalName,jobTitle,department,mobilePhone,businessPhones,accountEnabled,userType,assignedLicenses';
    $users = entraGraphGetAll("https://graph.microsoft.com/v1.0/users?\$select=$select&\$top=999", $token);

    if ($users === null) {
        throw new Exception("could not list users");
    }

    $synced = 0;
    $created = 0;
    $pushed_to_entra = 0;

    foreach ($users as $user) {
        // Guests (cross-tenant access, e.g. a shared meeting room account) are not
        // this business's staff and have no business phone/title to manage here.
        if (($user['userType'] ?? 'Member') !== 'Member') {
            continue;
        }

        $object_id = $user['id'];
        $email = firstNonEmpty($user['mail'] ?? '', $user['userPrincipalName'] ?? '');

        $link = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM entra_sync_contacts
            WHERE entra_sync_contact_tenant_id = $tenant_id AND entra_sync_contact_object_id = '" . sqlEsc($object_id) . "' LIMIT 1"));

        $contact_id = $link ? intval($link['entra_sync_contact_contact_id']) : 0;

        // Not linked yet - try to match an existing contact by email before creating a
        // new one, so a contact already in ITFlow gets adopted rather than duplicated.
        // Excludes contacts already claimed by a different Entra user - a shared mailbox
        // proxy address or similar oddity could otherwise match two users to one contact
        // and the second link INSERT would fail on entra_sync_contact_contact_id's UNIQUE key.
        if (!$contact_id && $email !== '') {
            $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id FROM contacts
                WHERE contact_client_id = $client_id AND contact_archived_at IS NULL
                AND LOWER(contact_email) = LOWER('" . sqlEsc($email) . "')
                AND contact_id NOT IN (SELECT entra_sync_contact_contact_id FROM entra_sync_contacts)
                LIMIT 1"));
            if ($existing) {
                $contact_id = intval($existing['contact_id']);
            }
        }

        $entra_name = (string) ($user['displayName'] ?? '');
        $entra_title = (string) ($user['jobTitle'] ?? '');
        $entra_department = (string) ($user['department'] ?? '');
        $entra_mobile_raw = (string) ($user['mobilePhone'] ?? '');
        $entra_business_raw = (string) (($user['businessPhones'][0] ?? ''));

        if (!$contact_id) {
            $mobile_parts = splitPhoneFromEntra($entra_mobile_raw);
            $phone_parts = splitPhoneFromEntra($entra_business_raw);

            mysqli_query($mysqli, "INSERT INTO contacts SET
                contact_client_id = $client_id,
                contact_name = '" . sqlEsc($entra_name) . "',
                contact_email = '" . sqlEsc($email) . "',
                contact_title = '" . sqlEsc($entra_title) . "',
                contact_department = '" . sqlEsc($entra_department) . "',
                contact_mobile_country_code = '" . sqlEsc($mobile_parts['country_code']) . "',
                contact_mobile = '" . sqlEsc($mobile_parts['number']) . "',
                contact_phone_country_code = '" . sqlEsc($phone_parts['country_code']) . "',
                contact_phone = '" . sqlEsc($phone_parts['number']) . "'");
            $contact_id = mysqli_insert_id($mysqli);
            $created++;

            logAudit("Contact", "Create", "Entra sync: created contact from $email", $client_id, $contact_id);
        }

        $contact_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = $contact_id LIMIT 1"));
        if (!$contact_row) {
            continue; // contact was deleted from under a stale link - next run re-links or re-creates
        }

        $itflow_mobile = combinePhoneForEntra($contact_row['contact_mobile_country_code'], $contact_row['contact_mobile']);
        $itflow_phone = combinePhoneForEntra($contact_row['contact_phone_country_code'], $contact_row['contact_phone']);

        // field key => [itflow current, entra current, snapshot column]
        $fields = [
            'name' => [$contact_row['contact_name'], $entra_name, 'entra_sync_contact_last_display_name'],
            'title' => [$contact_row['contact_title'], $entra_title, 'entra_sync_contact_last_title'],
            'department' => [$contact_row['contact_department'], $entra_department, 'entra_sync_contact_last_department'],
            'mobile' => [$itflow_mobile, $entra_mobile_raw, 'entra_sync_contact_last_mobile'],
            'business_phone' => [$itflow_phone, $entra_business_raw, 'entra_sync_contact_last_business_phone'],
        ];

        $contact_updates = [];
        $entra_patch_body = [];
        $pushed_snapshot_cols = []; // snapshot col => itflow value, only applied once the PATCH below succeeds
        $snapshot_updates = [];

        foreach ($fields as $key => [$itflow_value, $entra_value, $snapshot_col]) {
            $itflow_value = trim((string) $itflow_value);
            $entra_value = trim((string) $entra_value);
            $snapshot_value = trim((string) ($link[$snapshot_col] ?? ''));

            $itflow_changed = $link !== null && $itflow_value !== $snapshot_value;
            $entra_changed = $entra_value !== $snapshot_value;

            if ($writeback && $itflow_changed && !$entra_changed) {
                // ITFlow moved, Entra didn't - push ITFlow's value up. The snapshot isn't
                // moved to itflow_value until the PATCH below actually succeeds - if it
                // fails, leaving the old snapshot in place means this field is still
                // "itflow changed" next run and the push is retried, instead of the
                // failed push being forgotten and Entra's stale value pulled back down.
                switch ($key) {
                    case 'name': $entra_patch_body['displayName'] = $itflow_value; break;
                    case 'title': $entra_patch_body['jobTitle'] = $itflow_value; break;
                    case 'department': $entra_patch_body['department'] = $itflow_value; break;
                    case 'mobile': $entra_patch_body['mobilePhone'] = $itflow_value; break;
                    case 'business_phone': $entra_patch_body['businessPhones'] = $itflow_value === '' ? [] : [$itflow_value]; break;
                }
                $pushed_snapshot_cols[$snapshot_col] = $itflow_value;
            } elseif ($entra_changed) {
                // Entra moved (or this is the first sync, or writeback is off and ITFlow
                // moved instead) - Entra wins, including the case where both sides moved
                // since the last run.
                switch ($key) {
                    case 'name': $contact_updates['contact_name'] = $entra_value; break;
                    case 'title': $contact_updates['contact_title'] = $entra_value; break;
                    case 'department': $contact_updates['contact_department'] = $entra_value; break;
                    case 'mobile':
                        $parts = splitPhoneFromEntra($entra_value);
                        $contact_updates['contact_mobile_country_code'] = $parts['country_code'];
                        $contact_updates['contact_mobile'] = $parts['number'];
                        break;
                    case 'business_phone':
                        $parts = splitPhoneFromEntra($entra_value);
                        $contact_updates['contact_phone_country_code'] = $parts['country_code'];
                        $contact_updates['contact_phone'] = $parts['number'];
                        break;
                }
                $snapshot_updates[$snapshot_col] = $entra_value;
            } else {
                // Neither side moved relative to the snapshot - including a brand new
                // link, where the snapshot doesn't exist yet. Seed/keep it at the value
                // both sides already agree on (itflow_value, which by construction equals
                // entra_value here), not the old/absent snapshot - otherwise a first link
                // against a pre-existing contact permanently misreads that contact's
                // existing data as "changed since the snapshot" on every future run.
                $snapshot_updates[$snapshot_col] = $itflow_value;
            }
        }

        // Email is the match key - never overwritten either direction once linked.
        if ($contact_row['contact_email'] === '' || $contact_row['contact_email'] === null) {
            $contact_updates['contact_email'] = $email;
        }

        if (!empty($contact_updates)) {
            $set_sql = [];
            foreach ($contact_updates as $col => $value) {
                $set_sql[] = "`$col` = '" . sqlEsc($value) . "'";
            }
            mysqli_query($mysqli, "UPDATE contacts SET " . implode(', ', $set_sql) . " WHERE contact_id = $contact_id");
        }

        if (!empty($entra_patch_body)) {
            if (entraGraphPatch("https://graph.microsoft.com/v1.0/users/$object_id", $token, $entra_patch_body)) {
                $pushed_to_entra++;
                $snapshot_updates = array_merge($snapshot_updates, $pushed_snapshot_cols);
                logAudit("Contact", "Update", "Entra sync: pushed " . implode(', ', array_keys($entra_patch_body)) . " to Entra", $client_id, $contact_id);
            } else {
                // Patch failed - leave these columns out of snapshot_updates entirely so
                // the existing (older) snapshot value in the DB is left as-is by the
                // upsert below, and the field is retried next run.
                logApp("Cron-Entra-Sync", "error", "Failed to push contact $contact_id field update to Entra user $object_id");
            }
        }

        // License + sign-in status is read-only from Entra - surfaced as a managed
        // block in contact_notes since contacts has no dedicated column for it.
        $license_names = [];
        foreach (($user['assignedLicenses'] ?? []) as $license) {
            $sku_id = $license['skuId'] ?? '';
            if (isset($sku_names[$sku_id])) {
                $license_names[] = friendlySkuName($sku_names[$sku_id]);
            }
        }
        $license_names = array_unique($license_names);

        $notes_lines = [
            'Microsoft 365 licenses: ' . ($license_names ? implode(', ', $license_names) : 'None assigned'),
            'Entra sign-in: ' . (($user['accountEnabled'] ?? true) ? 'Enabled' : 'Disabled'),
            "Last Entra sync: $now",
        ];
        $new_notes = updateManagedNotesBlock((string) ($contact_row['contact_notes'] ?? ''), $notes_lines);
        if ($new_notes !== $contact_row['contact_notes']) {
            mysqli_query($mysqli, "UPDATE contacts SET contact_notes = '" . sqlEsc($new_notes) . "' WHERE contact_id = $contact_id");
        }

        // Upsert the link + field snapshot
        $snapshot_set = [];
        foreach ($snapshot_updates as $col => $value) {
            $snapshot_set[] = "`$col` = '" . sqlEsc($value) . "'";
        }
        $snapshot_set[] = "entra_sync_contact_last_synced_at = '$now'";

        if ($link) {
            mysqli_query($mysqli, "UPDATE entra_sync_contacts SET " . implode(', ', $snapshot_set) . "
                WHERE entra_sync_contact_id = " . intval($link['entra_sync_contact_id']));
        } else {
            mysqli_query($mysqli, "INSERT INTO entra_sync_contacts SET
                entra_sync_contact_tenant_id = $tenant_id,
                entra_sync_contact_object_id = '" . sqlEsc($object_id) . "',
                entra_sync_contact_contact_id = $contact_id,
                " . implode(', ', $snapshot_set));
        }

        $synced++;
    }

    return ['synced' => $synced, 'created' => $created, 'pushed_to_entra' => $pushed_to_entra];
}

/** ------------------------------------------------------------------
 * Asset (device) sync - one-directional, Entra/Intune -> ITFlow
 * ------------------------------------------------------------------ */

function entraSyncAssets(array $tenant, string $token): array {
    global $mysqli;

    $tenant_id = intval($tenant['entra_tenant_id']);
    $client_id = intval($tenant['entra_tenant_client_id']);
    $now = date('Y-m-d H:i:s');

    $select = 'id,displayName,operatingSystem,operatingSystemVersion,trustType,approximateLastSignInDateTime,accountEnabled,profileType';
    $devices = entraGraphGetAll("https://graph.microsoft.com/v1.0/devices?\$select=$select&\$top=999", $token);

    if ($devices === null) {
        throw new Exception("could not list devices");
    }

    $synced = 0;
    $created = 0;

    foreach ($devices as $device) {
        $device_id = $device['id'];
        $name = trim((string) ($device['displayName'] ?? ''));
        if ($name === '') {
            continue;
        }

        $link = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM entra_sync_assets
            WHERE entra_sync_asset_tenant_id = $tenant_id AND entra_sync_asset_device_id = '" . sqlEsc($device_id) . "' LIMIT 1"));

        $asset_id = $link ? intval($link['entra_sync_asset_asset_id']) : 0;

        if (!$asset_id) {
            // Entra device names aren't unique - the same physical machine can show up
            // twice after a reimage/re-join with a new device ID but the old display
            // name. Exclude assets already claimed by a different device link, or two
            // same-named devices would both try to adopt the one asset row and the
            // second link INSERT would fail on entra_sync_asset_asset_id's UNIQUE key.
            $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id FROM assets
                WHERE asset_client_id = $client_id AND asset_archived_at IS NULL
                AND asset_name = '" . sqlEsc($name) . "'
                AND asset_id NOT IN (SELECT entra_sync_asset_asset_id FROM entra_sync_assets)
                LIMIT 1"));
            if ($existing) {
                $asset_id = intval($existing['asset_id']);
            }
        }

        $os = trim((string) ($device['operatingSystem'] ?? ''));
        $os_version = trim((string) ($device['operatingSystemVersion'] ?? ''));
        $asset_os = trim("$os $os_version");
        $asset_type = in_array(strtolower($os), ['ios', 'android'], true) ? 'Mobile Phone' : 'Desktop';

        if (!$asset_id) {
            mysqli_query($mysqli, "INSERT INTO assets SET
                asset_client_id = $client_id,
                asset_type = '" . sqlEsc($asset_type) . "',
                asset_name = '" . sqlEsc($name) . "',
                asset_make = 'Unknown',
                asset_os = '" . sqlEsc($asset_os) . "',
                asset_status = 'Deployed'");
            $asset_id = mysqli_insert_id($mysqli);
            $created++;

            logAudit("Asset", "Create", "Entra sync: created asset from device $name", $client_id, $asset_id);
        } else {
            mysqli_query($mysqli, "UPDATE assets SET asset_os = '" . sqlEsc($asset_os) . "' WHERE asset_id = $asset_id");
        }

        $asset_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_notes FROM assets WHERE asset_id = $asset_id LIMIT 1"));
        if ($asset_row) {
            $trust_type = (string) ($device['trustType'] ?? '');
            $trust_label = $trust_type === 'AzureAd'
                ? 'Entra joined (company-owned)'
                : ($trust_type === 'ServerAd' ? 'Hybrid Entra joined' : 'Entra registered (BYOD / personal)');

            $last_signin = (string) ($device['approximateLastSignInDateTime'] ?? '');

            $notes_lines = [
                "Join type: $trust_label",
                'Last Entra sign-in: ' . ($last_signin !== '' ? $last_signin : 'Unknown'),
                'Entra sign-in enabled: ' . (($device['accountEnabled'] ?? true) ? 'Yes' : 'No'),
                "Last Entra sync: $now",
            ];
            $new_notes = updateManagedNotesBlock((string) ($asset_row['asset_notes'] ?? ''), $notes_lines);
            if ($new_notes !== $asset_row['asset_notes']) {
                mysqli_query($mysqli, "UPDATE assets SET asset_notes = '" . sqlEsc($new_notes) . "' WHERE asset_id = $asset_id");
            }
        }

        $now_esc = sqlEsc($now);
        if ($link) {
            mysqli_query($mysqli, "UPDATE entra_sync_assets SET entra_sync_asset_last_synced_at = '$now_esc' WHERE entra_sync_asset_id = " . intval($link['entra_sync_asset_id']));
        } else {
            mysqli_query($mysqli, "INSERT INTO entra_sync_assets SET
                entra_sync_asset_tenant_id = $tenant_id,
                entra_sync_asset_device_id = '" . sqlEsc($device_id) . "',
                entra_sync_asset_asset_id = $asset_id,
                entra_sync_asset_last_synced_at = '$now_esc'");
        }

        $synced++;
    }

    return ['synced' => $synced, 'created' => $created];
}

/** ------------------------------------------------------------------
 * Walk every enabled tenant
 * ------------------------------------------------------------------ */

$tenants_sql = mysqli_query($mysqli, "SELECT * FROM entra_tenants WHERE entra_tenant_enabled = 1");
if (!$tenants_sql || !mysqli_num_rows($tenants_sql)) {
    cronJobStop();
}

$summary = [];

while ($tenant = mysqli_fetch_assoc($tenants_sql)) {
    $tenant_id = intval($tenant['entra_tenant_id']);
    $tenant_name = $tenant['entra_tenant_name'];

    try {
        $token = entraGetAppToken($tenant['entra_tenant_directory_id'], $tenant['entra_tenant_app_id'], $tenant['entra_tenant_app_secret']);
        if ($token === null) {
            throw new Exception("could not acquire an app-only access token");
        }

        $sku_names = [];
        if ($tenant['entra_tenant_sync_licenses']) {
            $skus = entraGraphGetAll("https://graph.microsoft.com/v1.0/subscribedSkus", $token);
            foreach (($skus ?? []) as $sku) {
                $sku_names[$sku['skuId']] = $sku['skuPartNumber'];
            }
        }

        $result_line = [];

        if ($tenant['entra_tenant_sync_contacts']) {
            $contacts_result = entraSyncContacts($tenant, $token, $sku_names);
            $result_line[] = "{$contacts_result['synced']} contacts ({$contacts_result['created']} new, {$contacts_result['pushed_to_entra']} pushed to Entra)";
        }

        if ($tenant['entra_tenant_sync_assets']) {
            $assets_result = entraSyncAssets($tenant, $token);
            $result_line[] = "{$assets_result['synced']} devices ({$assets_result['created']} new)";
        }

        mysqli_query($mysqli, "UPDATE entra_tenants SET
            entra_tenant_last_sync_at = '" . date('Y-m-d H:i:s') . "',
            entra_tenant_last_error = NULL
            WHERE entra_tenant_id = $tenant_id");

        $summary[] = "$tenant_name: " . implode(', ', $result_line);

    } catch (Throwable $e) {
        $error_message = $e->getMessage();
        mysqli_query($mysqli, "UPDATE entra_tenants SET entra_tenant_last_error = '" . sqlEsc($error_message) . "' WHERE entra_tenant_id = $tenant_id");
        logApp("Cron-Entra-Sync", "error", "Entra sync failed for tenant $tenant_name: $error_message");
        $summary[] = "$tenant_name: FAILED - $error_message";
    }
}

$execution_time = number_format(microtime(true) - $script_start_time, 2);
logApp("Cron-Entra-Sync", "info", "Cron Entra Sync executed in $execution_time seconds. " . implode(' | ', $summary));

echo "Entra sync complete: " . implode(' | ', $summary) . "\n";
