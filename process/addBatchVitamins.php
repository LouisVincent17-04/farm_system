<?php
// process/addBatchVitamins.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';

session_start();

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { throw new Exception("Invalid data received."); }

    $date    = $input['date'] ?? date('Y-m-d H:i:s');
    $records = $input['records'] ?? [];

    if (empty($records)) { throw new Exception("No animals processed."); }

    $conn->beginTransaction();

    // 1. AGGREGATE NEEDS (Calculate total quantity needed per Item)
    $item_needs = [];
    foreach ($records as $row) {
        $iid = $row['item_id'];
        $qty = floatval($row['quantity']);
        
        if (empty($iid)) { throw new Exception("One or more animals have no supplement selected."); }
        if ($qty <= 0) { throw new Exception("Quantity must be greater than 0."); }

        if (!isset($item_needs[$iid])) $item_needs[$iid] = 0;
        $item_needs[$iid] += $qty;
    }

    // 2. VALIDATE STOCK & CALCULATE COSTS
    $unit_costs = [];
    $stockSql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME FROM VITAMINS_SUPPLEMENTS WHERE SUPPLY_ID = :id FOR UPDATE";
    $stockStmt = $conn->prepare($stockSql);

    foreach ($item_needs as $id => $needed) {
        $stockStmt->execute([':id' => $id]);
        $inv = $stockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$inv) { 
            throw new Exception("Item ID $id not found in inventory."); 
        }
        
        if ($inv['TOTAL_STOCK'] < $needed) {
            throw new Exception("Insufficient stock for '{$inv['SUPPLY_NAME']}'. Available: {$inv['TOTAL_STOCK']}, Needed: $needed");
        }

        // Calculate Average Cost (Prevent division by zero)
        $unit_costs[$id] = ($inv['TOTAL_STOCK'] > 0) ? ($inv['TOTAL_COST'] / $inv['TOTAL_STOCK']) : 0;
    }

    // 3. INSERT TRANSACTIONS
    $insertSql = "INSERT INTO VITAMINS_SUPPLEMENTS_TRANSACTIONS 
                  (ANIMAL_ID, ITEM_ID, TRANSACTION_DATE, QUANTITY_USED, REMARKS, TOTAL_COST, DOSAGE) 
                  VALUES 
                  (:aid, :iid, :date, :qty, :rem, :cost, :dos)";
    $insertStmt = $conn->prepare($insertSql);

    $inserted_count = 0;

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
        $inserted_count++;
    }

    // 4. DEDUCT INVENTORY
    $upSql = "UPDATE VITAMINS_SUPPLEMENTS 
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

    // 5. INSERT AUDIT LOG (Inside Transaction)
    if ($inserted_count > 0) {
        $audit_action = "BATCH_VITAMINS";
        $audit_details = "Processed $inserted_count vitamin/supplement records. Date: $date";

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'VITAMINS_SUPPLEMENTS_TRANSACTIONS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully processed " . count($records) . " records."]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>