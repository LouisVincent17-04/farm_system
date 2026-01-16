<?php
// process/addFeedTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// User Info
$user_id = $_SESSION['user']['USER_ID'] ?? null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']); exit;
}

try {
    // 1. Inputs
    $pen_id = $_POST['pen_id'];
    $feed_id = $_POST['feed_id'];
    $qty_per_head = floatval($_POST['qty_per_head']);
    $trans_date = str_replace('T', ' ', $_POST['transaction_date']) . ':00';

    if ($qty_per_head <= 0) throw new Exception("Quantity must be greater than 0");

    $conn->beginTransaction();

    // 2. Get Animals in Pen
    $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM ANIMAL_RECORDS WHERE PEN_ID = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS = 'Active'");
    $stmt->execute([$pen_id]);
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $animal_count = count($animals);
    if ($animal_count === 0) throw new Exception("No active animals found in this pen.");

    // 3. Calculate Total Deduction
    $total_deduction = $animal_count * $qty_per_head;

    // 4. Check Stock & Get Cost
    $stmt = $conn->prepare("SELECT FEED_NAME, TOTAL_WEIGHT_KG, TOTAL_COST FROM FEEDS WHERE FEED_ID = ? FOR UPDATE");
    $stmt->execute([$feed_id]);
    $feed = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($feed['TOTAL_WEIGHT_KG'] < $total_deduction) {
        throw new Exception("Insufficient Stock. Need: $total_deduction kg, Have: {$feed['TOTAL_WEIGHT_KG']} kg");
    }

    // Weighted Average Cost
    $cost_per_kg = ($feed['TOTAL_WEIGHT_KG'] > 0) ? ($feed['TOTAL_COST'] / $feed['TOTAL_WEIGHT_KG']) : 0;
    $cost_per_animal = $qty_per_head * $cost_per_kg;

    // 5. Generate a Unique BATCH ID
    $batch_id = uniqid('BATCH-', true);

    // 6. Prepare Statements
    // A. Feed Transaction Statement
    $insertSql = "INSERT INTO FEED_TRANSACTIONS (FEED_ID, ANIMAL_ID, TRANSACTION_DATE, QUANTITY_KG, TRANSACTION_COST, REMARKS, BATCH_ID) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);

    // B. Operational Cost Statement (NEW) 

    $opCostSql = "INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, ?)";
    $opCostStmt = $conn->prepare($opCostSql);

    // Get Pen Name for Remark
    $penName = $conn->query("SELECT PEN_NAME FROM PENS WHERE PEN_ID = $pen_id")->fetchColumn();
    $remarks = "Bulk Feed: $penName";
    $op_description = "Feed: " . $feed['FEED_NAME'] . " (" . $qty_per_head . "kg)";

    // 7. Loop Insert Transactions
    foreach ($animals as $animal) {
        // A. Insert into Feed Transactions
        $insertStmt->execute([
            $feed_id,
            $animal['ANIMAL_ID'],
            $trans_date,
            $qty_per_head,
            $cost_per_animal,
            $remarks,
            $batch_id 
        ]);

        // B. Insert into Operational Cost (NEW)
        if ($cost_per_animal > 0) {
            $opCostStmt->execute([
                $animal['ANIMAL_ID'],
                $cost_per_animal,
                $op_description,
                $trans_date
            ]);
        }
    }

    // 8. Update Inventory (Once for the total amount)
    $new_weight = $feed['TOTAL_WEIGHT_KG'] - $total_deduction;
    $new_cost = $feed['TOTAL_COST'] - ($total_deduction * $cost_per_kg);
    
    $upd = $conn->prepare("UPDATE FEEDS SET TOTAL_WEIGHT_KG = ?, TOTAL_COST = ?, DATE_UPDATED = NOW() WHERE FEED_ID = ?");
    $upd->execute([$new_weight, $new_cost, $feed_id]);

    // 9. Audit Log
    $audit_msg = "Bulk Feeding (Batch: $batch_id): Pen '$penName'. Fed $animal_count animals ($qty_per_head kg each). Total Deducted: $total_deduction kg.";
    
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'BULK_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $audit_msg, $ip]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully fed $animal_count animals! Total: $total_deduction kg."]);

} catch (Exception $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>