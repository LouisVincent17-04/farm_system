<?php
// process/reverseMedicationTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

$user_id = $_SESSION['user']['USER_ID'] ?? 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method.");
    }

    // --- GET TARGET BATCH DATE FROM FRONTEND ---
    $target_date = $_POST['batch_date'] ?? null;
    if (empty($target_date)) {
        throw new Exception("No target batch timestamp provided.");
    }

    $conn->beginTransaction();

    // 1. Fetch ALL info for this specific batch to process inventory restoration
    $sql = "SELECT tt.*, m.SUPPLY_NAME, a.TAG_NO 
            FROM treatment_transactions tt
            LEFT JOIN medicines m ON tt.ITEM_ID = m.SUPPLY_ID
            LEFT JOIN animal_records a ON tt.ANIMAL_ID = a.ANIMAL_ID
            WHERE tt.TRANSACTION_DATE = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$target_date]);
    $batch = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batch)) {
        throw new Exception("Batch records not found or already reversed.");
    }

    $restored_items = [];
    $animal_tags = [];

    // Prepared statements for loop efficiency
    $updateInv = $conn->prepare("UPDATE medicines SET TOTAL_STOCK = TOTAL_STOCK + ?, TOTAL_COST = TOTAL_COST + ? WHERE SUPPLY_ID = ?");
    $deleteOp = $conn->prepare("DELETE FROM operational_cost WHERE animal_id = ? AND datetime_created = ? AND description LIKE 'Medicine:%'");

    // 2. Loop through batch
    foreach ($batch as $row) {
        // Restore Medicine Inventory
        $updateInv->execute([$row['QUANTITY_USED'], $row['TOTAL_COST'], $row['ITEM_ID']]);
        
        // Remove from Operational Cost table
        $deleteOp->execute([$row['ANIMAL_ID'], $target_date]);

        $restored_items[] = $row['SUPPLY_NAME'];
        $animal_tags[] = $row['TAG_NO'];
    }

    // 3. Delete the Batch from transactions
    $deleteBatch = $conn->prepare("DELETE FROM treatment_transactions WHERE TRANSACTION_DATE = ?");
    $deleteBatch->execute([$target_date]);

    // 4. Audit Log
    $summary = "Restored: " . implode(', ', array_unique($restored_items)) . " for Animals: " . implode(', ', array_unique($animal_tags));
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'REVERSE_BATCH_MED', 'TREATMENT_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, "Batch Reversed ($target_date). $summary", $ip]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully reversed batch from $target_date (" . count($batch) . " records restored)."]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>