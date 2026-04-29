<?php
// process/updateWeights.php
error_reporting(0); // CRITICAL: Suppress all warnings so JSON does not break
ini_set('display_errors', 0);
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

// 2. Parse the JSON Payload
$payload_json = $_POST['payload'] ?? '[]';
$payload = json_decode($payload_json, true);
$weighing_date = $_POST['weighing_date'] ?? date('Y-m-d');
$remarks = 'Batch UI Update';

if (empty($payload) || !is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'No weight data received.']);
    exit;
}

try {
    // Extract all unique IDs to fetch class IDs in a single query
    $animal_ids = array_column($payload, 'id');
    
    // Safety check
    if(empty($animal_ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload format.']);
        exit;
    }

    // 3. Fetch CLASS_ID for validation
    $placeholders = implode(',', array_fill(0, count($animal_ids), '?'));
    $classStmt = $conn->prepare("SELECT ANIMAL_ID, CLASS_ID FROM animal_records WHERE ANIMAL_ID IN ($placeholders)");
    $classStmt->execute($animal_ids); 
    
    // Map ANIMAL_ID to CLASS_ID for quick lookup
    $animal_classes = [];
    while ($row = $classStmt->fetch(PDO::FETCH_ASSOC)) {
        $animal_classes[$row['ANIMAL_ID']] = intval($row['CLASS_ID']);
    }

    // 4. Start Transaction
    $conn->beginTransaction();
    $updatedCount = 0;

    foreach ($payload as $item) {
        $animalId = $item['id'];
        $classId = $animal_classes[$animalId] ?? 0;
        
        $updateFields = [];
        $params = [];

        // Check Birth Weight
        if (isset($item['birth']) && trim($item['birth']) !== '') {
            $updateFields[] = "WEIGHT_AT_BIRTH = ?";
            $params[] = floatval($item['birth']);
        }

        // Check Weaning Weight (WITH CLASS_ID VALIDATION)
        if (isset($item['weaning']) && trim($item['weaning']) !== '' && $classId > 1) {
            $updateFields[] = "WEANING_WEIGHT = ?";
            $params[] = floatval($item['weaning']);
        }

        // Check Current Weight
        if (isset($item['current']) && trim($item['current']) !== '') {
            $updateFields[] = "CURRENT_ACTUAL_WEIGHT = ?";
            $params[] = floatval($item['current']);
        }

        // Skip if no weights provided or if they tried to bypass UI disabling for weaning
        if (empty($updateFields)) {
            continue;
        }

        // Add the updated timestamp
        $updateFields[] = "UPDATED_AT = NOW()";
        
        // Build the dynamic SQL query
        $sql = "UPDATE animal_records SET " . implode(", ", $updateFields) . " WHERE ANIMAL_ID = ?";
        $params[] = $animalId;

        // Execute
        $updateStmt = $conn->prepare($sql);
        $updateStmt->execute($params);
        $updatedCount++;
    }

    // 5. Commit Transaction & Audit Log
    if ($updatedCount > 0) {
        
        // --- INSERT AUDIT LOG ---
        $audit_action = "BATCH_WEIGHT_UPDATE";
        $audit_details = "Updated weight records (Birth/Weaning/Current) for $updatedCount animals. Date: $weighing_date. Remarks: $remarks";

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
    // 6. Rollback on Error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => "Database error occurred."
    ]);
}
?>