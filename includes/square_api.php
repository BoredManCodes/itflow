<?php
/**
 * Minimal Square Payments API client.
 *
 * Square's official PHP SDK pulls in a large dependency tree (Guzzle, apimatic/core, etc.)
 * for functionality this integration doesn't need yet - a single create-payment REST call.
 * Talking to the REST API directly with cURL avoids vendoring an SDK that can't be exercised
 * in this environment before deploy.
 */

// Pinned rather than left unset - an unpinned request silently defaults to whatever version
// the Square application was created under, which drifts out from under us over time.
DEFINE("SQUARE_API_VERSION", "2026-08-19");

/**
 * Square issues sandbox Application IDs with a "sandbox-" prefix - that's the only reliable
 * signal for which API host and Web Payments SDK script a stored provider row belongs to.
 */
function squareIsSandbox($application_id) {
    return str_starts_with($application_id, 'sandbox-');
}

/**
 * @throws RuntimeException on a transport failure or a non-2xx response
 */
function squareApiRequest($method, $endpoint, $access_token, $sandbox, $body = null) {
    $base_url = $sandbox ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';

    $ch = curl_init($base_url . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Square-Version: ' . SQUARE_API_VERSION,
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("Square API request failed: $curl_error");
    }

    $decoded = json_decode($response, true);

    if ($http_code >= 400) {
        $message = $decoded['errors'][0]['detail'] ?? "Square API error (HTTP $http_code)";
        throw new RuntimeException($message);
    }

    return $decoded;
}
