<?php
// process/addBatchVaccination.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';

session_start();

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

try {
    // 1. Get JSON Input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception("Invalid data received.");
    }

    // 2. Extract & Validate Common Data
    $vaccine_id = $input['vaccine_id'] ?? null;
    $unit_id    = $input['unit_id'] ?? null;
    $vet_name   = $input['vet_name'] ?? 'Unknown';
    $date       = $input['date'] ?? date('Y-m-d H:i:s');
    $records    = $input['records'] ?? [];
    
    // Optional service fee per head
    $service_fee = isset($input['service_fee']) ? floatval($input['service_fee']) : 0;

    if (empty($vaccine_id) || empty($records)) {
        throw new Exception("Missing required vaccine or animal selection.");
    }

    // Start Transaction
    $conn->beginTransaction();

    // 3. Check Inventory & Calculate Cost
    $stockSql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME FROM VACCINES WHERE SUPPLY_ID = :id FOR UPDATE";
    $stockStmt = $conn->prepare($stockSql);
    $stockStmt->execute([':id' => $vaccine_id]);
    $inventory = $stockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        throw new Exception("Vaccine not found in inventory.");
    }

    // Calculate total quantity needed for this batch
    $total_qty_needed = 0;
    foreach ($records as $rec) {
        $total_qty_needed += floatval($rec['quantity']);
    }

    // Validate Stock Levels
    if ($inventory['TOTAL_STOCK'] < $total_qty_needed) {
        throw new Exception("Insufficient stock! Available: {$inventory['TOTAL_STOCK']}, Needed: {$total_qty_needed}");
    }

    // Calculate Average Cost Per Unit
    $current_unit_cost = ($inventory['TOTAL_STOCK'] > 0) 
        ? ($inventory['TOTAL_COST'] / $inventory['TOTAL_STOCK']) 
        : 0;

    // 4. Prepare Insert Statement
    $insertSql = "INSERT INTO VACCINATION_RECORDS 
        (ANIMAL_ID, ITEM_ID, VACCINATION_DATE, VET_NAME, REMARKS, QUANTITY, UNIT_ID, VACCINE_COST, VACCINATION_COST) 
        VALUES 
        (:animal_id, :vaccine_id, :date, :vet, :remarks, :qty, :unit, :item_cost, :service_cost)";
    
    $insertStmt = $conn->prepare($insertSql);

    // 5. Loop & Insert Records
    $inserted_count = 0;
    foreach ($records as $row) {
        $qty_used = floatval($row['quantity']);
        $item_cost = $qty_used * $current_unit_cost; 

        $insertStmt->execute([
            ':animal_id'    => $row['animal_id'],
            ':vaccine_id'   => $vaccine_id,
            ':date'         => $date,
            ':vet'          => $vet_name,
            ':remarks'      => $row['remarks'] ?? '',
            ':qty'          => $qty_used,
            ':unit'         => $unit_id,
            ':item_cost'    => $item_cost,
            ':service_cost' => $service_fee 
        ]);
        $inserted_count++;
    }

    // 6. Deduct from Inventory
    $total_cost_deducted = $total_qty_needed * $current_unit_cost;

    $updateSql = "UPDATE VACCINES 
                  SET TOTAL_STOCK = TOTAL_STOCK - :qty, 
                      TOTAL_COST = TOTAL_COST - :cost 
                  WHERE SUPPLY_ID = :id";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([
        ':qty'  => $total_qty_needed,
        ':cost' => $total_cost_deducted,
        ':id'   => $vaccine_id
    ]);

    // 7. Insert Audit Log (Inside Transaction)
    if ($inserted_count > 0) {
        $supply_name = $inventory['SUPPLY_NAME'] ?? 'Unknown Vaccine';
        $audit_action = "BATCH_VACCINATION";
        $audit_details = "Vaccinated $inserted_count animals with $supply_name. Total Qty: $total_qty_needed. Vet: $vet_name";

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'VACCINATION_RECORDS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
    }

    // 8. Commit Transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => "Batch processed for " . count($records) . " animals."]);

} catch (Exception $e) {
    // Rollback on any error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>