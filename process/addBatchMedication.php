<?php
// process/addBatchMedication.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';
session_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { throw new Exception("Invalid data received."); }

    $date    = $input['date'] ?? date('Y-m-d H:i:s');
    $records = $input['records'] ?? [];

    if (empty($records)) { throw new Exception("No records to process."); }

    $conn->beginTransaction();

    // 1. CALCULATE NEEDS (Item ID => Total Qty)
    $item_needs = [];
    foreach ($records as $row) {
        $iid = $row['item_id'];
        $qty = floatval($row['quantity']);
        
        if (empty($iid)) { throw new Exception("Missing medication for one or more entries."); }
        if ($qty <= 0) { throw new Exception("Quantity must be greater than 0."); }

        if (!isset($item_needs[$iid])) $item_needs[$iid] = 0;
        $item_needs[$iid] += $qty;
    }

    // 2. CHECK STOCK & GET COSTS
    $unit_costs = [];
    $stockStmt = $conn->prepare("SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME FROM MEDICINES WHERE SUPPLY_ID = :id FOR UPDATE");

    foreach ($item_needs as $id => $needed) {
        $stockStmt->execute([':id' => $id]);
        $inv = $stockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) { throw new Exception("Medicine ID $id not found."); }
        
        if ($inv['TOTAL_STOCK'] < $needed) {
            throw new Exception("Insufficient stock for '{$inv['SUPPLY_NAME']}'. Available: {$inv['TOTAL_STOCK']}, Needed: $needed");
        }

        // Avg Cost
        $unit_costs[$id] = ($inv['TOTAL_STOCK'] > 0) ? ($inv['TOTAL_COST'] / $inv['TOTAL_STOCK']) : 0;
    }

    // 3. INSERT TRANSACTIONS
    $insertSql = "INSERT INTO TREATMENT_TRANSACTIONS 
                  (ANIMAL_ID, ITEM_ID, TRANSACTION_DATE, QUANTITY_USED, REMARKS, TOTAL_COST, DOSAGE) 
                  VALUES 
                  (:aid, :iid, :date, :qty, :rem, :cost, :dos)";
    $insertStmt = $conn->prepare($insertSql);

    foreach ($records as $row) {
        $iid = $row['item_id'];
        $qty = floatval($row['quantity']);
        $cost = $qty * $unit_costs[$iid];
        $dosage = isset($row['dosage']) ? $row['dosage'] : '';

        $insertStmt->execute([
            ':aid'  => $row['animal_id'],
            ':iid'  => $iid,
            ':date' => $date,
            ':qty'  => $qty,
            ':rem'  => $row['remarks'] ?? '',
            ':cost' => $cost,
            ':dos'  => $dosage
        ]);
    }

    // 4. DEDUCT INVENTORY
    $upSql = "UPDATE MEDICINES 
              SET TOTAL_STOCK = TOTAL_STOCK - :qty, 
                  TOTAL_COST = TOTAL_COST - :cost 
              WHERE SUPPLY_ID = :id";
    $upStmt = $conn->prepare($upSql);

    foreach ($item_needs as $id => $total_qty) {
        $total_cost_deducted = $total_qty * $unit_costs[$id];
        $upStmt->execute([
            ':qty'  => $total_qty,
            ':cost' => $total_cost_deducted,
            ':id'   => $id
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully processed " . count($records) . " treatments."]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>