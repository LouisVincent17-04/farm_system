<?php
// common/register_runner.php
// Called by test_runner_server.py when it starts on a device
// Stores: device name → ngrok URL mapping

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true);
$device = trim($body['device'] ?? '');
$url    = trim($body['url']    ?? '');

if (!$device || !$url) {
    echo json_encode(['success' => false, 'message' => 'Missing device or url']);
    exit;
}

// Store in a simple JSON file (or use DB if preferred)
$store_file = __DIR__ . '/runner_registry.json';
$registry   = [];

if (file_exists($store_file)) {
    $registry = json_decode(file_get_contents($store_file), true) ?? [];
}

$registry[$device] = [
    'url'        => $url,
    'registered' => date('Y-m-d H:i:s')
];

file_put_contents($store_file, json_encode($registry, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => "Registered: $device → $url"]);