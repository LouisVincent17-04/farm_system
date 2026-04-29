<?php
// ============================================================
// chat_endpoint.php  ← Place this in your web ROOT
// this is chat_endpoint.php
// This is the ONLY file that talks to the Python Flask API
// Do NOT include this file anywhere — it's a standalone endpoint
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// FIX 1: Removed :80 — HTTPS runs on 443, not 80. Forcing :80
// causes cURL to fail the TLS handshake entirely.
// FIX 2: Pointed to the actual network IP of the Python server instead of localhost
define('CHATBOT_API_URL', 'http://10.1.1.33:5000/chat');

// ── Read JSON body from fetch() ──────────────────────────────
$raw     = file_get_contents('php://input');
$body    = json_decode($raw, true);
$message = isset($body['message']) ? trim($body['message']) : '';

if ($message === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'No message provided.',
        'links'   => []
    ]);
    exit;
}

// ── Forward to Python Flask API ──────────────────────────────
$ch = curl_init(CHATBOT_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'ngrok-skip-browser-warning: true',  // bypass ngrok interstitial page
    ],
    CURLOPT_POSTFIELDS     => json_encode(['message' => $message]),
    CURLOPT_TIMEOUT        => 7,
    CURLOPT_CONNECTTIMEOUT => 3,
]);

$response  = curl_exec($ch);
$curl_err  = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ── Handle cURL errors ───────────────────────────────────────
if ($curl_err) {
    echo json_encode([
        'status'  => 'error',
        'message' => '  Could not reach AI server. Make sure Python is running. (' . $curl_err . ')',
        'links'   => []
    ]);
    exit;
}

// ── Handle bad HTTP response ─────────────────────────────────
if ($http_code !== 200) {
    echo json_encode([
        'status'  => 'error',
        'message' => '  AI server returned HTTP ' . $http_code,
        'links'   => []
    ]);
    exit;
}

// ── Forward Flask response directly to browser ───────────────
$decoded = json_decode($response, true);

if (!$decoded) {
    echo json_encode([
        'status'  => 'error',
        'message' => '  Invalid response from AI server.',
        'links'   => []
    ]);
    exit;
}

echo json_encode($decoded);
?>