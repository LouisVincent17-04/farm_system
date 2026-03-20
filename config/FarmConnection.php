<?php
require_once __DIR__ . '/SadminConnection.php';

function getFarmConnection(int $farm_id): PDO {
    static $pool = [];
    if (isset($pool[$farm_id])) return $pool[$farm_id];
    global $conn;

    // Step 1: farm_id → db_key
    $stmt = $conn->prepare("SELECT db_key, farm_status FROM farms WHERE farm_id = ? LIMIT 1");
    $stmt->execute([$farm_id]);
    $farm = $stmt->fetch();
    if (!$farm) throw new RuntimeException("Farm #$farm_id not found.");
    if ((int)$farm['farm_status'] !== 1) throw new RuntimeException("Farm #$farm_id is not active.");
    if (empty($farm['db_key'])) throw new RuntimeException("Farm #$farm_id has no database assigned.");

    // Step 2: db_key → credentials
    $stmt2 = $conn->prepare("SELECT db_name, db_host, db_user, db_pass, db_port FROM database_connections WHERE db_key = ? AND is_active = 1 LIMIT 1");
    $stmt2->execute([$farm['db_key']]);
    $creds = $stmt2->fetch();
    if (!$creds) throw new RuntimeException("No active connection record for farm #$farm_id.");

    $dsn = "mysql:host={$creds['db_host']};port={$creds['db_port']};dbname={$creds['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $creds['db_user'], $creds['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pool[$farm_id] = $pdo;
    return $pdo;
}

function assertFarmAccess(int $farm_id): void {
    if (($_SESSION['role'] ?? '') === 'superadmin') return;
    global $conn;
    $stmt = $conn->prepare("
        SELECT 1 FROM assigned_farms af
        JOIN farms f ON f.farm_id = af.farm_id
        WHERE af.user_id = ? AND af.farm_id = ? AND f.farm_status = 1
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $farm_id]);
    if (!$stmt->fetch()) throw new RuntimeException("Access denied to farm #$farm_id.");
}