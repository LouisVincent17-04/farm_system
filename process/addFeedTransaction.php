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
    $animal_ids = $_POST['animal_ids'] ?? [];
    $feed_id = $_POST['feed_id'] ?? null;
    $qty_per_head = floatval($_POST['qty_per_head'] ?? 0);
    $trans_date = str_replace('T', ' ', $_POST['transaction_date']) . ':00';

    if (empty($animal_ids)) throw new Exception("No animals selected.");
    if (!$feed_id) throw new Exception("Please select a feed.");
    if ($qty_per_head <= 0) throw new Exception("Quantity must be greater than 0");

    $conn->beginTransaction();

    $animal_count = count($animal_ids);

    // 2. Fetch specific Animal Data to ensure they exist and get tags/pens
    // Create placeholders like ?,?,? 
    $placeholders = implode(',', array_fill(0, $animal_count, '?'));
    
    // Fetch TAG_NO and PEN_ID to create accurate remarks
    $stmt = $conn->prepare("
        SELECT a.ANIMAL_ID, a.TAG_NO, p.PEN_NAME 
        FROM ANIMAL_RECORDS a 
        LEFT JOIN PENS p ON a.PEN_ID = p.PEN_ID 
        WHERE a.ANIMAL_ID IN ($placeholders)
    ");
    $stmt->execute($animal_ids);
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Extract unique pen names for the batch remark
    $unique_pens = array_unique(array_column($animals, 'PEN_NAME'));
    $pen_names_str = implode(', ', array_filter($unique_pens));
    if(empty($pen_names_str)) $pen_names_str = 'Various / Unassigned';

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
    $insertSql = "INSERT INTO FEED_TRANSACTIONS (FEED_ID, ANIMAL_ID, TRANSACTION_DATE, QUANTITY_KG, TRANSACTION_COST, REMARKS, BATCH_ID) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);

    $opCostSql = "INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, ?)";
    $opCostStmt = $conn->prepare($opCostSql);

    $remarks = "Bulk Feed: Pens ($pen_names_str)";
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

        // B. Insert into Operational Cost
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
    
    // Safety check to prevent negative cost decimals
    if($new_weight <= 0) $new_cost = 0; 
    
    $upd = $conn->prepare("UPDATE FEEDS SET TOTAL_WEIGHT_KG = ?, TOTAL_COST = ?, DATE_UPDATED = NOW() WHERE FEED_ID = ?");
    $upd->execute([$new_weight, $new_cost, $feed_id]);

    // 9. Audit Log
    $audit_msg = "Bulk Feeding (Batch: $batch_id) for Pens: '$pen_names_str'. Fed $animal_count animals ($qty_per_head kg each). Total Deducted: $total_deduction kg.";
    
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'BULK_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $audit_msg, $ip]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully fed $animal_count animals! Total: $total_deduction kg."]);

} catch (Exception $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>