<?php
// process/transferGroupProcess.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// 1. Get Inputs
$dest_loc_id = $_POST['dest_location_id'] ?? '';
$dest_bld_id = $_POST['dest_building_id'] ?? '';
$dest_pen_id = $_POST['dest_pen_id'] ?? '';
$animal_ids  = $_POST['animal_ids'] ?? []; // Array of IDs

// 2. Validate
if (empty($dest_loc_id) || empty($dest_bld_id) || empty($dest_pen_id)) {
    echo json_encode(['success' => false, 'message' => 'Please select a full destination (Location, Building, and Pen).']);
    exit;
}

if (empty($animal_ids) || !is_array($animal_ids)) {
    echo json_encode(['success' => false, 'message' => 'No animals selected for transfer.']);
    exit;
}

try {
    $conn->beginTransaction();

    // 0. Fetch Destination Details for Audit Log (Human-readable names)
    $locStmt = $conn->prepare("
        SELECT 
            l.LOCATION_NAME, b.BUILDING_NAME, p.PEN_NAME 
        FROM pens p
        JOIN buildings b ON p.BUILDING_ID = b.BUILDING_ID
        JOIN locations l ON b.LOCATION_ID = l.LOCATION_ID
        WHERE p.PEN_ID = ? AND b.BUILDING_ID = ? AND l.LOCATION_ID = ?
    ");
    $locStmt->execute([$dest_pen_id, $dest_bld_id, $dest_loc_id]);
    $destInfo = $locStmt->fetch(PDO::FETCH_ASSOC);
    
    $destString = $destInfo ? "{$destInfo['LOCATION_NAME']} > {$destInfo['BUILDING_NAME']} > {$destInfo['PEN_NAME']}" : "Unknown Destination";

    // 3. Perform Updates
    $sql = "UPDATE animal_records 
            SET LOCATION_ID = ?, BUILDING_ID = ?, PEN_ID = ? 
            WHERE ANIMAL_ID = ?";
    $stmt = $conn->prepare($sql);

    $count = 0;
    foreach ($animal_ids as $id) {
        $stmt->execute([$dest_loc_id, $dest_bld_id, $dest_pen_id, $id]);
        $count++;
    }

    // 4. Insert Audit Log
    if ($count > 0) {
        $audit_action = "GROUP_TRANSFER";
        $audit_details = "Transferred $count animals to: $destString";

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'ANIMAL_RECORDS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully transferred $count animals."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => "Database Error: " . $e->getMessage()
    ]);
}
?>