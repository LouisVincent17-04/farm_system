<?php
// process/saveWeights.php
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
    // We update the current actual weight.
    // Note: If you want to track history later, you would add an INSERT into a history table here.
    $updateStmt = $conn->prepare("
        UPDATE animal_records 
        SET CURRENT_ACTUAL_WEIGHT = ?, 
            UPDATED_AT = NOW() 
        WHERE ANIMAL_ID = ?
    ");

    $updatedCount = 0;

    foreach ($weights as $animalId => $weightVal) {
        // Validation: Ensure weight is a valid number and strictly positive
        // We allow '0' only if you specifically want to reset it, but usually weight > 0
        // Skip empty strings to prevent overwriting existing data with nothing
        if ($weightVal === '' || $weightVal === null) {
            continue;
        }

        $weight = floatval($weightVal);

        if ($weight > 0) {
            $updateStmt->execute([$weight, $animalId]);
            $updatedCount++;
        }
    }

    // 4. Commit Transaction
    if ($updatedCount > 0) {
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
    error_log("Weight Update Error: " . $e->getMessage()); // Log error to server file
    echo json_encode([
        'success' => false, 
        'message' => "Database error occurred. Please try again."
    ]);
}
?>