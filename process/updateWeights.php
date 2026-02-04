<?php
// process/updateWeights.php
header('Content-Type: application/json');
include '../config/Connection.php';
session_start();

// 1. Security & Validation
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// 2. Input Retrieval
$weights = $_POST['weights'] ?? []; // Expected format: [animal_id => weight_value, ...]
$remarks = $_POST['remarks'] ?? '';
$weighing_date = $_POST['weighing_date'] ?? date('Y-m-d');

if (empty($weights)) {
    echo json_encode(['success' => false, 'message' => 'No weight data received.']);
    exit;
}

try {
    // 3. Start Transaction
    $conn->beginTransaction();

    // Prepare Update Statement
    $updateStmt = $conn->prepare("
        UPDATE animal_records 
        SET CURRENT_ACTUAL_WEIGHT = ?, 
            UPDATED_AT = NOW() 
        WHERE ANIMAL_ID = ?
    ");

    $updatedCount = 0;

    foreach ($weights as $animalId => $weightVal) {
        // Validation: Ensure weight is a valid number and strictly positive
        if ($weightVal === '' || $weightVal === null) {
            continue;
        }

        $weight = floatval($weightVal);

        if ($weight > 0) {
            $updateStmt->execute([$weight, $animalId]);
            $updatedCount++;
        }
    }

    // 4. Commit Transaction & Audit Log
    if ($updatedCount > 0) {
        
        // --- INSERT AUDIT LOG ---
        $audit_action = "BATCH_WEIGHT_UPDATE";
        $audit_details = "Updated weights for $updatedCount animals. Date: $weighing_date. Remarks: " . ($remarks ?: 'None');

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'ANIMAL_RECORDS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully updated weights for $updatedCount animals."
        ]);
    } else {
        $conn->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "No valid weight changes were detected to save."
        ]);
    }

} catch (Exception $e) {
    // 5. Rollback on Error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Weight Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => "Database error occurred: " . $e->getMessage()
    ]);
}
?>