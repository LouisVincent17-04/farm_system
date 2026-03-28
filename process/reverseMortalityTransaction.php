<?php
// process/reverseMortalityTransaction.php
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
    $sql = "SELECT s.*, a.TAG_NO 
            FROM animal_sales s
            LEFT JOIN animal_records a ON s.animal_id = a.ANIMAL_ID
            WHERE s.sale_date = ? AND s.transaction_type = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$target_date]);
    $batch_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batch_transactions)) {
        throw new Exception("Batch mortality records not found or already reversed.");
    }

    $total_restored_count = count($batch_transactions);
    $animal_tags = [];

    // Prepared statement to restore the animal's status in the farm
    $updateAnimal = $conn->prepare("UPDATE animal_records 
                                    SET CURRENT_STATUS = 'Active', 
                                        IS_ACTIVE = 1,
                                        UPDATED_AT = NOW() 
                                    WHERE ANIMAL_ID = ?");

    // 2. Process each transaction in the batch
    foreach ($batch_transactions as $trans) {
        $animal_id = $trans['animal_id'];
        
        // A. Resurrect Animal (Set to Active)
        $updateAnimal->execute([$animal_id]);

        // Track for logs
        $animal_tags[] = $trans['TAG_NO'];
    }

    // 3. Delete the Mortality Records for this batch
    $deleteTrans = $conn->prepare("DELETE FROM animal_sales WHERE sale_date = ? AND transaction_type = 0");
    $deleteTrans->execute([$target_date]);

    // 4. Audit Log
    $summary = "Animals Resurrected: " . implode(', ', array_unique($animal_tags));
    $details = "Batch Mortality Reversal at $target_date. Restored $total_restored_count animals to Active status. $summary";
    
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_BATCH_MORTALITY', 'ANIMAL_SALES', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Reversal Successful! Restored $total_restored_count animals back to active inventory."
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