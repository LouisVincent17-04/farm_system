<?php
// common/debug_runner.php
// TEMPORARY — shows what's in the registry
// DELETE after debugging!

header('Content-Type: application/json');

$store_file = __DIR__ . '/runner_registry.json';

if (!file_exists($store_file)) {
    echo json_encode(['error' => 'runner_registry.json does not exist']);
    exit;
}

$registry = json_decode(file_get_contents($store_file), true);
echo json_encode($registry, JSON_PRETTY_PRINT);