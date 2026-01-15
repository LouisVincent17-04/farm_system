<?php
// process/transferGroupProcess.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user']['USER_ID'] ?? 1; // Default to admin if no session

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

    // Prepare Update Statement
    $sql = "UPDATE animal_records 
            SET LOCATION_ID = ?, BUILDING_ID = ?, PEN_ID = ? 
            WHERE ANIMAL_ID = ?";
    $stmt = $conn->prepare($sql);

    $count = 0;

    foreach ($animal_ids as $id) {
        $stmt->execute([$dest_loc_id, $dest_bld_id, $dest_pen_id, $id]);
        $count++;
        
        // Optional: Insert into an 'animal_movement_history' table here if you have one.
        // INSERT INTO animal_movement (ANIMAL_ID, FROM_PEN, TO_PEN, DATE, USER) ...
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