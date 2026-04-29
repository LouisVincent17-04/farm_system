<?php
// common/chat_run_test.php
// Looks up the calling device's registered runner URL, then forwards the test trigger

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only Super Admins (USER_TYPE = 4) can trigger tests
if (!isset($_SESSION['user']['USER_TYPE']) || $_SESSION['user']['USER_TYPE'] != 4) {
    echo json_encode([
        'success' => false,
        'message' => '🚫 Access denied. Only Super Admins can run tests.'
    ]);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$test_key = strtolower(trim($body['test']   ?? ''));
$device   = trim($body['device'] ?? '');

if (empty($test_key) || empty($device)) {
    echo json_encode(['success' => false, 'message' => '  Missing test or device name.']);
    exit;
}

// Look up this device's runner URL from registry
$store_file = __DIR__ . '/runner_registry.json';

if (!file_exists($store_file)) {
    echo json_encode([
        'success' => false,
        'message' => '  No devices registered. Run test_runner_server.py on your device first.'
    ]);
    exit;
}

$registry = json_decode(file_get_contents($store_file), true) ?? [];

if (!isset($registry[$device])) {
    echo json_encode([
        'success' => false,
        'message' => "  Device '<strong>$device</strong>' is not registered. Run test_runner_server.py on this device first."
    ]);
    exit;
}

$runner_url = rtrim($registry[$device]['url'], '/') . '/run-test';

// Forward the test trigger to this device's runner
$ch = curl_init($runner_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => $test_key]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err || !$response) {
    echo json_encode([
        'success' => false,
        'message' => "  Could not reach runner on '<strong>$device</strong>'. Make sure test_runner_server.py is still running."
    ]);
    exit;
}

$data = json_decode($response, true);
echo json_encode($data ?? ['success' => false, 'message' => 'Invalid response from runner.']);