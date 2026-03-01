<?php
// common/get_runners.php
// Returns list of registered device names for the chatbot device picker

header('Content-Type: application/json');

$store_file = __DIR__ . '/runner_registry.json';

if (!file_exists($store_file)) {
    echo json_encode(['devices' => []]);
    exit;
}

$registry = json_decode(file_get_contents($store_file), true) ?? [];
echo json_encode(['devices' => array_keys($registry)]);