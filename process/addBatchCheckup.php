<?php
// process/addBatchCheckup.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';

session_start();

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System) if missing
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

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

        // Format the final remark string: "[Healthy] Routine check"
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

    // 6. Insert Audit Log (Inside Transaction)
    // This ensures the log is only created if the checkups are successfully saved
    if ($inserted_count > 0) {
        $audit_action = "BATCH_ADD";
        $audit_details = "Recorded check-ups for $inserted_count animals. Vet: $vet_name. Date: $date";

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'CHECK_UPS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
    }

    // 7. Commit Transaction
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