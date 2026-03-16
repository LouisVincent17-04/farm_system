<?php
// process/reverseVitaminTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// User Context
$user_id = $_SESSION['user']['USER_ID'] ?? 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method.");
    }

    $conn->beginTransaction();

    // 1. Identify the LATEST timestamp used in the vitamin table
    $timeSql = "SELECT TRANSACTION_DATE FROM vitamins_supplements_transactions ORDER BY TRANSACTION_DATE DESC LIMIT 1";
    $timeStmt = $conn->prepare($timeSql);
    $timeStmt->execute();
    $latest_time = $timeStmt->fetchColumn();

    if (!$latest_time) {
        throw new Exception("No vitamin transactions found to reverse.");
    }

    // 2. Fetch ALL transactions that occurred at this exact timestamp
    $sql = "SELECT vt.*, v.SUPPLY_NAME, a.TAG_NO 
            FROM vitamins_supplements_transactions vt
            LEFT JOIN vitamins_supplements v ON vt.ITEM_ID = v.SUPPLY_ID
            LEFT JOIN animal_records a ON vt.ANIMAL_ID = a.ANIMAL_ID
            WHERE vt.TRANSACTION_DATE = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$latest_time]);
    $batch_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batch_transactions)) {
        throw new Exception("Error retrieving batch records.");
    }

    $total_restored_count = count($batch_transactions);
    $restored_items = [];
    $animal_tags = [];

    // Prepared statements for the loop
    $updateInv = $conn->prepare("UPDATE vitamins_supplements 
                                 SET TOTAL_STOCK = TOTAL_STOCK + ?, 
                                     TOTAL_COST = TOTAL_COST + ?, 
                                     DATE_UPDATED = NOW() 
                                 WHERE SUPPLY_ID = ?");

    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE animal_id = ? 
                                AND datetime_created = ?");

    // 3. Process each transaction in the batch
    foreach ($batch_transactions as $trans) {
        $item_id = $trans['ITEM_ID'];
        $restore_qty = $trans['QUANTITY_USED'];
        $restore_cost = $trans['TOTAL_COST'];
        $animal_id = $trans['ANIMAL_ID'];

        // A. Restore Inventory
        $updateInv->execute([$restore_qty, $restore_cost, $item_id]);

        // B. Remove Financial Impact from operational_cost
        $deleteOp->execute([$animal_id, $latest_time]);

        // Track for logs
        $restored_items[] = $trans['SUPPLY_NAME'];
        $animal_tags[] = $trans['TAG_NO'];
    }

    // 4. Delete the transaction records for this batch
    $deleteTrans = $conn->prepare("DELETE FROM vitamins_supplements_transactions WHERE TRANSACTION_DATE = ?");
    $deleteTrans->execute([$latest_time]);

    // 5. Audit Log
    $summary = "Restored: " . implode(', ', array_unique($restored_items)) . " for Animals: " . implode(', ', array_unique($animal_tags));
    $details = "Batch Reversal at $latest_time. Removed $total_restored_count records. $summary";
    
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_BATCH_VITAMIN', 'VITAMINS_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Batch Reversal Successful! Removed $total_restored_count vitamin records and restored inventory."
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