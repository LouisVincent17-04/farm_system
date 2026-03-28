<?php
// process/reverseFeedingTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// User Context for Audit Log
$user_id = $_SESSION['user']['USER_ID'] ?? 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method.");
    }

    // --- THE FIX: Get the specific batch ID sent from the frontend ---
    $batch_id = $_POST['batch_id'] ?? null;
    if (empty($batch_id)) {
        throw new Exception("No Batch ID provided for reversal.");
    }

    $conn->beginTransaction();

    // 1. Verify the Batch exists and get its Transaction Date
    $stmt = $conn->prepare("SELECT TRANSACTION_DATE FROM feed_transactions WHERE BATCH_ID = ? LIMIT 1");
    $stmt->execute([$batch_id]);
    $target_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_trans) {
        throw new Exception("Batch ID {$batch_id} not found or already reversed.");
    }
    
    $trans_date = $target_trans['TRANSACTION_DATE'];

    // 2. Get Data needed for Restoration (Feed ID, Total Qty, Total Cost, Involved Animals)
    // We aggregate because a batch might involve multiple animals but (usually) one feed type.
    $stmt = $conn->prepare("SELECT FEED_ID, SUM(QUANTITY_KG) as total_qty, SUM(TRANSACTION_COST) as total_cost 
                            FROM feed_transactions 
                            WHERE BATCH_ID = ? 
                            GROUP BY FEED_ID");
    $stmt->execute([$batch_id]);
    $feed_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feed_data) {
        throw new Exception("Corrupted transaction data. Cannot reverse.");
    }

    $feed_id = $feed_data['FEED_ID'];
    $restore_qty = $feed_data['total_qty'];
    $restore_cost = $feed_data['total_cost'];

    // Get list of animals involved to safely delete operational costs
    $stmt = $conn->prepare("SELECT ANIMAL_ID FROM feed_transactions WHERE BATCH_ID = ?");
    $stmt->execute([$batch_id]);
    $animal_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($animal_ids)) {
        throw new Exception("No animals linked to this batch.");
    }

    // 3. Restore Inventory (Quantity & Cost)
    // We add back the used amount and the value to keep Weighted Average Cost accurate.
    $updateFeed = $conn->prepare("UPDATE feeds 
                                  SET TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + ?, 
                                      TOTAL_COST = TOTAL_COST + ?, 
                                      DATE_UPDATED = NOW() 
                                  WHERE FEED_ID = ?");
    $updateFeed->execute([$restore_qty, $restore_cost, $feed_id]);

    // 4. Remove Financial Impact (Operational Cost)
    // We delete rows matching the specific animals and the exact timestamp of the transaction.
    $placeholders = implode(',', array_fill(0, count($animal_ids), '?'));
    
    // Merge params: [trans_date, animal_id_1, animal_id_2, ...]
    $op_params = array_merge([$trans_date], $animal_ids);
    
    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE datetime_created = ? 
                                AND animal_id IN ($placeholders)
                                AND description LIKE 'Feed:%'"); // Extra safety check
    $deleteOp->execute($op_params);

    // 5. Delete the Transaction Records
    $deleteTrans = $conn->prepare("DELETE FROM feed_transactions WHERE BATCH_ID = ?");
    $deleteTrans->execute([$batch_id]);

    // 6. Audit Log
    $details = "Reversed Feeding Batch: $batch_id. Restored $restore_qty kg to Feed #$feed_id.";
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Batch {$batch_id} reversed! Restored " . number_format($restore_qty, 2) . "kg to inventory."
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