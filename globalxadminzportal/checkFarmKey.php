<?php
// globalxadminzportal/checkFarmKey.php
// Called by register.php via fetch() to validate a farm_code in real time.
// Returns JSON: { valid: bool, farm_name: string|null, message: string }
//
// DOES NOT require authentication — called from the public registration page.

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../config/SadminConnection.php';

$farm_code = strtoupper(trim($_GET['farm_code'] ?? ''));

if (empty($farm_code)) {
    echo json_encode(['valid' => false, 'message' => 'Farm Code is required.']);
    exit;
}

if (strlen($farm_code) < 4) {
    echo json_encode(['valid' => false, 'message' => 'Farm Code is too short.']);
    exit;
}

// Only allow alphanumeric and dash
if (!preg_match('/^[A-Z0-9\-]+$/', $farm_code)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid Farm Code format.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT  f.farm_name,
                f.farm_status,
                dc.is_active AS dc_active
        FROM    farms f
        LEFT JOIN database_connections dc ON dc.db_key = f.db_key
        WHERE   f.farm_code = ?
        LIMIT   1
    ");
    $stmt->execute([$farm_code]);
    $farm = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$farm) {
        echo json_encode(['valid' => false, 'message' => 'Farm Code not found. Please check the code and try again.']);
        exit;
    }

    if ((int)$farm['farm_status'] !== 1) {
        echo json_encode(['valid' => false, 'message' => 'This farm is currently inactive. Contact your farm owner.']);
        exit;
    }

    if ($farm['dc_active'] !== null && !(int)$farm['dc_active']) {
        echo json_encode(['valid' => false, 'message' => 'This farm database is unavailable. Contact support.']);
        exit;
    }

    echo json_encode([
        'valid'     => true,
        'farm_name' => $farm['farm_name'],
        'message'   => 'Farm found.',
    ]);

} catch (Exception $e) {
    error_log("checkFarmKey.php error: " . $e->getMessage());
    echo json_encode(['valid' => false, 'message' => 'Verification failed. Please try again.']);
}