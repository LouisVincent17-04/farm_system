<?php
// process/reverseCheckupTransaction.php
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

    // --- THE FIX: Get the target batch date sent from the frontend ---
    $target_date = $_POST['batch_date'] ?? null;
    if (empty($target_date)) {
        throw new Exception("No target batch timestamp provided for reversal.");
    }

    $conn->beginTransaction();

    // 1. Fetch ALL transactions that occurred at this exact timestamp
    $sql = "SELECT c.*, a.TAG_NO 
            FROM check_ups c
            LEFT JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID
            WHERE c.CHECKUP_DATE = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$target_date]);
    $batch_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batch_transactions)) {
        throw new Exception("Batch records not found or already reversed.");
    }

    $total_restored_count = count($batch_transactions);
    $total_cost_removed = 0;
    $animal_tags = [];

    // Prepared statement for removing financial impact (Operational Cost)
    // Added 'description LIKE' safety check
    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE animal_id = ? 
                                AND datetime_created = ?
                                AND description LIKE 'Check-up:%'");

    // 2. Process each transaction in the batch
    foreach ($batch_transactions as $trans) {
        $animal_id = $trans['ANIMAL_ID'];
        $total_cost_removed += $trans['COST'];
        
        // Remove Financial Impact from operational_cost
        $deleteOp->execute([$animal_id, $target_date]);

        // Track for logs
        $animal_tags[] = $trans['TAG_NO'];
    }

    // 3. Delete the check-up records for this batch
    $deleteTrans = $conn->prepare("DELETE FROM check_ups WHERE CHECKUP_DATE = ?");
    $deleteTrans->execute([$target_date]);

    // 4. Audit Log
    $summary = "Animals: " . implode(', ', array_unique($animal_tags));
    $details = "Batch Reversal at $target_date. Removed $total_restored_count check-up records. Total cost removed: ₱" . number_format($total_cost_removed, 2) . ". $summary";
    
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_BATCH_CHECKUP', 'CHECK_UPS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Batch Reversal Successful! Removed $total_restored_count check-up records and reversed costs."
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>