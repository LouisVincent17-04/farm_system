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
$birth_weights = $_POST['birth_weights'] ?? []; 
$weaning_weights = $_POST['weaning_weights'] ?? []; 
$current_weights = $_POST['current_weights'] ?? []; 

$remarks = $_POST['remarks'] ?? '';
$weighing_date = $_POST['weighing_date'] ?? date('Y-m-d');

// Combine all unique animal IDs from the three arrays
$animal_ids = array_unique(array_merge(
    array_keys($birth_weights),
    array_keys($weaning_weights),
    array_keys($current_weights)
));

if (empty($animal_ids)) {
    echo json_encode(['success' => false, 'message' => 'No weight data received.']);
    exit;
}

try {
    // 3. Fetch CLASS_ID for validation
    // Create placeholders for the IN clause (e.g., ?,?,?)
    $placeholders = implode(',', array_fill(0, count($animal_ids), '?'));
    $classStmt = $conn->prepare("SELECT ANIMAL_ID, CLASS_ID FROM animal_records WHERE ANIMAL_ID IN ($placeholders)");
    $classStmt->execute(array_values($animal_ids));
    
    // Map ANIMAL_ID to CLASS_ID for quick lookup
    $animal_classes = [];
    while ($row = $classStmt->fetch(PDO::FETCH_ASSOC)) {
        $animal_classes[$row['ANIMAL_ID']] = intval($row['CLASS_ID']);
    }

    // 4. Start Transaction
    $conn->beginTransaction();

    $updatedCount = 0;

    foreach ($animal_ids as $animalId) {
        $updateFields = [];
        $params = [];
        $classId = $animal_classes[$animalId] ?? 0; // Get the specific animal's class ID

        // Check Birth Weight
        if (isset($birth_weights[$animalId]) && $birth_weights[$animalId] !== '') {
            $updateFields[] = "WEIGHT_AT_BIRTH = ?";
            $params[] = floatval($birth_weights[$animalId]);
        }

        // Check Weaning Weight (WITH CLASS_ID VALIDATION)
        if (isset($weaning_weights[$animalId]) && $weaning_weights[$animalId] !== '' && $classId > 1) {
            $updateFields[] = "WEANING_WEIGHT = ?";
            $params[] = floatval($weaning_weights[$animalId]);
        }

        // Check Current Weight
        if (isset($current_weights[$animalId]) && $current_weights[$animalId] !== '') {
            $updateFields[] = "CURRENT_ACTUAL_WEIGHT = ?";
            $params[] = floatval($current_weights[$animalId]);
        }

        // If no weights were provided (or if the only update was a weaning weight but class was <= 1), skip
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
        $audit_details = "Updated weight records (Birth/Weaning/Current) for $updatedCount animals. Date: $weighing_date. Remarks: " . ($remarks ?: 'None');

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
            'message' => "No valid weight changes were detected to save (Note: Weaning weights require Class > 1)."
        ]);
    }

} catch (Exception $e) {
    // 6. Rollback on Error
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