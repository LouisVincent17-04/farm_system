<?php
// process/reverseVaccinationTransaction.php
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

    // 1. Identify the LATEST timestamp used in the vaccination table
    $timeSql = "SELECT VACCINATION_DATE FROM vaccination_records ORDER BY VACCINATION_DATE DESC LIMIT 1";
    $timeStmt = $conn->prepare($timeSql);
    $timeStmt->execute();
    $latest_time = $timeStmt->fetchColumn();

    if (!$latest_time) {
        throw new Exception("No vaccination records found to reverse.");
    }

    // 2. Fetch ALL transactions that occurred at this exact timestamp
    $sql = "SELECT v.*, vac.SUPPLY_NAME, a.TAG_NO 
            FROM vaccination_records v
            LEFT JOIN vaccines vac ON v.ITEM_ID = vac.SUPPLY_ID
            LEFT JOIN animal_records a ON v.ANIMAL_ID = a.ANIMAL_ID
            WHERE v.VACCINATION_DATE = ?";
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
    // Note: VACCINATION_COST (Service Fee) is NOT restored to inventory value, only VACCINE_COST is.
    $updateInv = $conn->prepare("UPDATE vaccines 
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
        $restore_qty = $trans['QUANTITY'];
        $restore_val = $trans['VACCINE_COST']; 
        $animal_id = $trans['ANIMAL_ID'];

        // A. Restore Inventory
        $updateInv->execute([$restore_qty, $restore_val, $item_id]);

        // B. Remove Financial Impact from operational_cost
        $deleteOp->execute([$animal_id, $latest_time]);

        // Track for logs
        $restored_items[] = $trans['SUPPLY_NAME'];
        $animal_tags[] = $trans['TAG_NO'];
    }

    // 4. Delete the transaction records for this batch
    $deleteTrans = $conn->prepare("DELETE FROM vaccination_records WHERE VACCINATION_DATE = ?");
    $deleteTrans->execute([$latest_time]);

    // 5. Audit Log
    $summary = "Restored: " . implode(', ', array_unique($restored_items)) . " for Animals: " . implode(', ', array_unique($animal_tags));
    $details = "Batch Reversal at $latest_time. Removed $total_restored_count vaccination records. $summary";
    
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_BATCH_VACCINE', 'VACCINATION_RECORDS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Batch Reversal Successful! Removed $total_restored_count vaccination records and restored inventory."
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