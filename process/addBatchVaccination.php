<?php
// process/addBatchVaccination.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';

// Ensure user is authorized
session_start();
// if (!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 2) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
//     exit;
// }

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
    
    // Optional service fee per head (if sent by JS, otherwise 0)
    $service_fee = isset($input['service_fee']) ? floatval($input['service_fee']) : 0;

    if (empty($vaccine_id) || empty($records)) {
        throw new Exception("Missing required vaccine or animal selection.");
    }

    // Start Transaction
    $conn->beginTransaction();

    // 3. Check Inventory & Calculate Cost
    // We lock the row 'FOR UPDATE' to prevent race conditions during deduction
    $stockSql = "SELECT TOTAL_STOCK, TOTAL_COST FROM VACCINES WHERE SUPPLY_ID = :id FOR UPDATE";
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

    // Calculate Average Cost Per Unit (Total Cost / Total Stock)
    // Avoid division by zero
    $current_unit_cost = ($inventory['TOTAL_STOCK'] > 0) 
        ? ($inventory['TOTAL_COST'] / $inventory['TOTAL_STOCK']) 
        : 0;

    // 4. Prepare Insert Statement
    $insertSql = "INSERT INTO VACCINATION_RECORDS 
        (ANIMAL_ID, VACCINE_ITEM_ID, VACCINATION_DATE, VET_NAME, REMARKS, QUANTITY, UNIT_ID, VACCINE_COST, VACCINATION_COST) 
        VALUES 
        (:animal_id, :vaccine_id, :date, :vet, :remarks, :qty, :unit, :item_cost, :service_cost)";
    
    $insertStmt = $conn->prepare($insertSql);

    // 5. Loop & Insert Records
    foreach ($records as $row) {
        $qty_used = floatval($row['quantity']);
        $item_cost = $qty_used * $current_unit_cost; // Cost of the specific dose

        $insertStmt->execute([
            ':animal_id'   => $row['animal_id'],
            ':vaccine_id'  => $vaccine_id,
            ':date'        => $date,
            ':vet'         => $vet_name,
            ':remarks'     => $row['remarks'] ?? '',
            ':qty'         => $qty_used,
            ':unit'        => $unit_id,
            ':item_cost'   => $item_cost,
            ':service_cost'=> $service_fee // Fee per animal
        ]);
    }

    // 6. Deduct from Inventory
    // We deduct both the physical stock and the monetary value associated with it
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

    // 7. Commit Transaction
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