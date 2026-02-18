<?php
// process/reverseMedicationTransaction.php
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

    // 1. Find the Most Recent Medication Transaction
    $sql = "SELECT tt.*, m.SUPPLY_NAME 
            FROM treatment_transactions tt
            LEFT JOIN medicines m ON tt.ITEM_ID = m.SUPPLY_ID
            ORDER BY tt.TT_ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_trans) {
        throw new Exception("No medication transactions found to reverse.");
    }

    $tt_id = $last_trans['TT_ID'];
    $item_id = $last_trans['ITEM_ID'];
    $animal_id = $last_trans['ANIMAL_ID'];
    $restore_qty = $last_trans['QUANTITY_USED'];
    $restore_cost = $last_trans['TOTAL_COST']; // Monetary value to restore to inventory valuation
    $trans_date = $last_trans['TRANSACTION_DATE'];

    // 2. Restore Inventory (Medicines Table)
    // Increase Stock and Total Value back to what it was
    $updateMed = $conn->prepare("UPDATE medicines 
                                 SET TOTAL_STOCK = TOTAL_STOCK + ?, 
                                     TOTAL_COST = TOTAL_COST + ?, 
                                     DATE_UPDATED = NOW() 
                                 WHERE SUPPLY_ID = ?");
    $updateMed->execute([$restore_qty, $restore_cost, $item_id]);

    // 3. Remove Financial Impact (Operational Cost)
    // Match by Animal ID and exact Timestamp to ensure we delete the correct cost record
    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE animal_id = ? 
                                AND datetime_created = ?");
    $deleteOp->execute([$animal_id, $trans_date]);

    // 4. Delete the Transaction Record
    $deleteTrans = $conn->prepare("DELETE FROM treatment_transactions WHERE TT_ID = ?");
    $deleteTrans->execute([$tt_id]);

    // 5. Audit Log
    $details = "Reversed Medication ID #$tt_id. Restored $restore_qty units of '{$last_trans['SUPPLY_NAME']}'.";
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_MED', 'TREATMENT_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Reversal Successful! Restored {$last_trans['SUPPLY_NAME']} stock."
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