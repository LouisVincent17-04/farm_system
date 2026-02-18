<?php
// process/addSingleFeedTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// User Info (Safe Fallback)
$user_id = $_SESSION['user']['USER_ID'] ?? null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']); exit;
}

try {
    // 1. Retrieve Inputs
    $animal_id = $_POST['animal_id'] ?? null;
    $feed_id = $_POST['feed_id'] ?? null;
    $qty_kg = floatval($_POST['qty_per_head'] ?? 0);
    $trans_date = str_replace('T', ' ', $_POST['transaction_date']) . ':00'; // Ensure MySQL format

    // 2. Validate Inputs
    if (!$animal_id || !$feed_id) throw new Exception("Missing Animal or Feed ID.");
    if ($qty_kg <= 0) throw new Exception("Quantity must be greater than 0 kg.");

    $conn->beginTransaction();

    // 3. Get Animal Details (Tag & Active Status)
    $stmt = $conn->prepare("SELECT TAG_NO, CURRENT_STATUS FROM ANIMAL_RECORDS WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) throw new Exception("Animal not found or inactive.");

    // 4. Check Feed Stock & Calculate Cost
    $stmt = $conn->prepare("SELECT FEED_NAME, TOTAL_WEIGHT_KG, TOTAL_COST FROM FEEDS WHERE FEED_ID = ? FOR UPDATE");
    $stmt->execute([$feed_id]);
    $feed = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feed) throw new Exception("Feed item not found.");
    
    // Check sufficient stock
    if ($feed['TOTAL_WEIGHT_KG'] < $qty_kg) {
        throw new Exception("Insufficient Stock. Available: {$feed['TOTAL_WEIGHT_KG']} kg, Required: $qty_kg kg.");
    }

    // Weighted Average Cost Calculation
    // Cost Per Kg = Total Cost / Total Weight
    $cost_per_kg = ($feed['TOTAL_WEIGHT_KG'] > 0) ? ($feed['TOTAL_COST'] / $feed['TOTAL_WEIGHT_KG']) : 0;
    $transaction_cost = $qty_kg * $cost_per_kg;

    // 5. Insert Transaction Records
    
    // A. Feed Transaction
    // Use a unique Batch ID even for singles to maintain consistency with bulk structure
    $batch_id = uniqid('SGL-', true);
    $remarks = "Individual Feeding: Tag " . $animal['TAG_NO'];

    $sqlFeed = "INSERT INTO FEED_TRANSACTIONS 
                (FEED_ID, ANIMAL_ID, TRANSACTION_DATE, QUANTITY_KG, TRANSACTION_COST, REMARKS, BATCH_ID) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtFeed = $conn->prepare($sqlFeed);
    $stmtFeed->execute([
        $feed_id,
        $animal_id,
        $trans_date,
        $qty_kg,
        $transaction_cost,
        $remarks,
        $batch_id
    ]);

    // B. Operational Cost (Linked to Animal)
    if ($transaction_cost > 0) {
        $opDescription = "Feed: " . $feed['FEED_NAME'] . " (" . number_format($qty_kg, 2) . "kg)";
        
        $sqlOp = "INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) 
                  VALUES (?, ?, ?, ?)";
        $stmtOp = $conn->prepare($sqlOp);
        $stmtOp->execute([
            $animal_id,
            $transaction_cost,
            $opDescription,
            $trans_date
        ]);
    }

    // 6. Update Feed Inventory
    $new_weight = $feed['TOTAL_WEIGHT_KG'] - $qty_kg;
    $new_total_cost = $feed['TOTAL_COST'] - $transaction_cost;

    // Prevent negative cost due to floating point precision
    if ($new_total_cost < 0) $new_total_cost = 0; 

    $upd = $conn->prepare("UPDATE FEEDS SET TOTAL_WEIGHT_KG = ?, TOTAL_COST = ?, DATE_UPDATED = NOW() WHERE FEED_ID = ?");
    $upd->execute([$new_weight, $new_total_cost, $feed_id]);

    // 7. Audit Log
    $log_details = "Fed Animal {$animal['TAG_NO']} with $qty_kg kg of {$feed['FEED_NAME']}. Cost: " . number_format($transaction_cost, 2);
    
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'SINGLE_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $log_details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully fed Tag {$animal['TAG_NO']}!"
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>