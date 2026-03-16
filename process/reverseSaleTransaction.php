<?php
// process/reverseSaleTransaction.php
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

    // 1. Identify the LATEST timestamp used in the sales table
    $timeSql = "SELECT sale_date FROM ANIMAL_SALES ORDER BY sale_date DESC LIMIT 1";
    $timeStmt = $conn->prepare($timeSql);
    $timeStmt->execute();
    $latest_time = $timeStmt->fetchColumn();

    if (!$latest_time) {
        throw new Exception("No sale records found to reverse.");
    }

    // 2. Fetch ALL sale transactions that occurred at this exact timestamp
    $sql = "SELECT s.*, a.TAG_NO 
            FROM ANIMAL_SALES s
            LEFT JOIN animal_records a ON s.animal_id = a.ANIMAL_ID
            WHERE s.sale_date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$latest_time]);
    $batch_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batch_transactions)) {
        throw new Exception("Error retrieving batch records.");
    }

    $total_restored_count = count($batch_transactions);
    $total_revenue_removed = 0;
    $animal_tags = [];

    // Prepared statement to restore the animal's status in the farm
    // Assuming CURRENT_STATUS goes back to 'Active' and IS_ACTIVE is 1
    $restoreAnimal = $conn->prepare("UPDATE animal_records 
                                     SET CURRENT_STATUS = 'Active', 
                                         IS_ACTIVE = 1 
                                     WHERE ANIMAL_ID = ?");

    // 3. Process each transaction in the batch
    foreach ($batch_transactions as $trans) {
        $animal_id = $trans['animal_id'];
        $total_revenue_removed += $trans['final_sale_price'];
        
        // A. Restore Animal Status
        $restoreAnimal->execute([$animal_id]);

        // Track for logs
        $animal_tags[] = $trans['TAG_NO'];
    }

    // 4. Delete the sales records for this batch
    $deleteTrans = $conn->prepare("DELETE FROM ANIMAL_SALES WHERE sale_date = ?");
    $deleteTrans->execute([$latest_time]);

    // 5. Audit Log
    $summary = "Animals Restored: " . implode(', ', array_unique($animal_tags));
    $details = "Batch Sale Reversal at $latest_time. Reversed $total_restored_count sales. Removed Revenue: ₱" . number_format($total_revenue_removed, 2) . ". $summary";
    
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_BATCH_SALE', 'ANIMAL_SALES', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Sale Reversal Successful! Restored $total_restored_count animals back to Active inventory."
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