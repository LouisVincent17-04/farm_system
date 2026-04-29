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
    $feeds_json = $_POST['feeds'] ?? '[]';
    $feeds = json_decode($feeds_json, true);
    
    // Safely format the datetime
    $raw_date = $_POST['transaction_date'] ?? date('Y-m-d H:i');
    $trans_date = str_replace('T', ' ', $raw_date);
    if (strlen($trans_date) == 16) { $trans_date .= ':00'; }

    if (empty($animal_ids)) throw new Exception("No animals selected.");
    if (empty($feeds)) throw new Exception("No feeds selected.");

    $animal_count = count($animal_ids);

    $conn->beginTransaction();

    // 2. Fetch specific Animal Data to get tags/pens for remarks
    $placeholders = implode(',', array_fill(0, $animal_count, '?'));
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

    // 3. Prepare Statements (Prepare once, execute many times for performance)
    $checkFeedStmt = $conn->prepare("SELECT FEED_NAME, TOTAL_WEIGHT_KG, TOTAL_COST FROM FEEDS WHERE FEED_ID = ? FOR UPDATE");
    $updateFeedStmt = $conn->prepare("UPDATE FEEDS SET TOTAL_WEIGHT_KG = ?, TOTAL_COST = ?, DATE_UPDATED = NOW() WHERE FEED_ID = ?");
    $insertTransStmt = $conn->prepare("INSERT INTO FEED_TRANSACTIONS (FEED_ID, ANIMAL_ID, TRANSACTION_DATE, QUANTITY_KG, TRANSACTION_COST, REMARKS, BATCH_ID) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $opCostStmt = $conn->prepare("INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, ?)");

    // 4. Generate a Unique BATCH ID for this entire multi-feed transaction
    $batch_id = uniqid('BATCH-', true);
    $total_deducted_overall = 0;
    $feed_names_used = [];

    // 5. Process EACH selected feed
    foreach ($feeds as $feed_item) {
        $feed_id = $feed_item['feed_id'];
        $qty_per_head = floatval($feed_item['qty_per_head']);

        if ($qty_per_head <= 0) continue; // Skip invalid quantities

        $total_deduction = $animal_count * $qty_per_head;

        // Fetch feed details & lock row
        $checkFeedStmt->execute([$feed_id]);
        $feed = $checkFeedStmt->fetch(PDO::FETCH_ASSOC);

        if (!$feed || $feed['TOTAL_WEIGHT_KG'] < $total_deduction) {
            $fname = $feed ? $feed['FEED_NAME'] : "Unknown Feed";
            throw new Exception("Insufficient Stock for {$fname}. Need: {$total_deduction} kg, Have: " . ($feed['TOTAL_WEIGHT_KG'] ?? 0) . " kg");
        }

        // Calculate Weighted Average Cost
        $cost_per_kg = ($feed['TOTAL_WEIGHT_KG'] > 0) ? ($feed['TOTAL_COST'] / $feed['TOTAL_WEIGHT_KG']) : 0;
        $cost_per_animal = $qty_per_head * $cost_per_kg;

        $remarks = "Bulk Feed: Pens ($pen_names_str)";
        $op_description = "Feed: " . $feed['FEED_NAME'] . " (" . $qty_per_head . "kg)";

        // Insert records for every animal
        foreach ($animals as $animal) {
            // A. Insert into Feed Transactions
            $insertTransStmt->execute([
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

        // Update Inventory for this specific feed
        $new_weight = $feed['TOTAL_WEIGHT_KG'] - $total_deduction;
        $new_cost = $feed['TOTAL_COST'] - ($total_deduction * $cost_per_kg);
        
        // Safety check to prevent floating point negative anomalies
        if($new_weight <= 0.001) { 
            $new_weight = 0; 
            $new_cost = 0; 
        } 
        
        $updateFeedStmt->execute([$new_weight, $new_cost, $feed_id]);

        $total_deducted_overall += $total_deduction;
        $feed_names_used[] = $feed['FEED_NAME'];
    }

    // 6. Audit Log
    if ($total_deducted_overall > 0) {
        $feed_list_str = implode(', ', $feed_names_used);
        $audit_msg = "Bulk Feeding (Batch: $batch_id) for Pens: '$pen_names_str'. Feeds applied: [$feed_list_str]. Fed $animal_count animals. Total Inventory Deducted: $total_deducted_overall kg.";
        
        $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'BULK_FEED', 'FEED_TRANSACTIONS', ?, ?)");
        $audit->execute([$user_id, $username, $audit_msg, $ip]);
    } else {
        throw new Exception("No valid feed quantities were processed.");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully fed $animal_count animals! Total: $total_deducted_overall kg distributed."]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>