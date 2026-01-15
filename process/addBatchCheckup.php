<?php
// process/addBatchCheckup.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';

// Ensure user is authorized (Vet or Admin)
session_start();
// if (!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 2 && $_SESSION['ROLE'] != 3) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
//     exit;
// }

try {
    // 1. Get JSON Input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception("Invalid data received.");
    }

    // 2. Extract & Validate Data
    $vet_name = $input['vet_name'] ?? 'Unknown';
    $date     = $input['date'] ?? date('Y-m-d H:i:s');
    $records  = $input['records'] ?? [];
    
    // Optional service fee per head
    $cost_per_head = isset($input['cost']) ? floatval($input['cost']) : 0;

    if (empty($records)) {
        throw new Exception("No animals selected for inspection.");
    }

    if (empty($vet_name)) {
        throw new Exception("Veterinarian name is required.");
    }

    // 3. Start Transaction
    $conn->beginTransaction();

    // 4. Prepare Insert Statement
    // We combine the selected 'Status' and typed 'Remarks' into the database REMARKS field
    // Format: "[Healthy] Routine check"
    $sql = "INSERT INTO CHECK_UPS 
            (ANIMAL_ID, CHECKUP_DATE, VET_NAME, REMARKS, COST) 
            VALUES 
            (:animal_id, :date, :vet, :final_remarks, :cost)";
    
    $stmt = $conn->prepare($sql);

    // 5. Loop & Insert
    $inserted_count = 0;

    foreach ($records as $row) {
        $animal_id = $row['animal_id'];
        $status    = $row['status'] ?? 'Healthy';
        $user_note = $row['remarks'] ?? '';

        // Format the final remark string
        $final_remarks = "[$status]";
        if (!empty($user_note)) {
            $final_remarks .= " " . $user_note;
        }

        $stmt->execute([
            ':animal_id'     => $animal_id,
            ':date'          => $date,
            ':vet'           => $vet_name,
            ':final_remarks' => $final_remarks,
            ':cost'          => $cost_per_head
        ]);

        $inserted_count++;
    }

    // 6. Commit Transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully recorded check-ups for $inserted_count animals."
    ]);

} catch (Exception $e) {
    // Rollback on any error to prevent partial data
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>